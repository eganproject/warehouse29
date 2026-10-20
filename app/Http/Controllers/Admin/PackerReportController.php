<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PackerScanOut;
use App\Models\Resi;
use App\Models\User;
use App\Support\TokoFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PackerReportController extends Controller
{
    public function index()
    {
        $packerIds = PackerScanOut::query()
            ->whereNotNull('scanned_by')
            ->distinct()
            ->pluck('scanned_by');

        $packers = User::query()
            ->whereIn('id', $packerIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.outbound.packer-reports.index', [
            'dataUrl' => route('admin.reports.packer-reports.data'),
            'packers' => $packers,
            'tokos' => TokoFilter::tokoOptions(),
            'today' => now()->toDateString(),
        ]);
    }

    public function data(Request $request)
    {
        $query = DB::table('packer_scan_outs as ps')
            ->join('users as u', 'u.id', '=', 'ps.scanned_by')
            ->selectRaw('ps.scan_date, ps.scanned_by, u.name as packer_name, COUNT(*) as total_scan, COUNT(DISTINCT ps.scan_code) as unique_scan, MIN(ps.scanned_at) as first_scan, MAX(ps.scanned_at) as last_scan')
            ->groupBy('ps.scan_date', 'ps.scanned_by', 'u.name');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('u.name', 'like', "%{$search}%")
                    ->orWhere('ps.scan_date', 'like', "%{$search}%");
            });
        }

        $packerId = $request->input('packer_id');
        if ($packerId) {
            $query->where('ps.scanned_by', $packerId);
        }

        $tokoId = TokoFilter::fromRequest($request);
        TokoFilter::applyViaResi($query, 'ps.resi_id', $tokoId);

        $dateFrom = $this->parseDate($request->input('date_from'));
        $dateTo = $this->parseDate($request->input('date_to'));
        if ($dateFrom) {
            $query->where('ps.scan_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('ps.scan_date', '<=', $dateTo);
        }

        $rows = $query
            ->orderByDesc('ps.scan_date')
            ->orderBy('packer_name')
            ->limit(500)
            ->get();

        $data = $rows->map(function ($row) {
            $first = $row->first_scan ? Carbon::parse($row->first_scan) : null;
            $last = $row->last_scan ? Carbon::parse($row->last_scan) : null;
            $durationHours = null;
            if ($first && $last) {
                $minutes = max(0, $first->diffInMinutes($last));
                $durationHours = $minutes > 0 ? $minutes / 60 : null;
            }
            $avgPerHour = $durationHours && $durationHours > 0
                ? round($row->total_scan / $durationHours, 2)
                : (int) $row->total_scan;

            return [
                'date' => Carbon::parse($row->scan_date)->format('Y-m-d'),
                'packer' => $row->packer_name ?? '-',
                'total_scan' => (int) $row->total_scan,
                'unique_scan' => (int) $row->unique_scan,
                'avg_per_hour' => $avgPerHour,
                'first_scan' => $first?->format('H:i') ?? '-',
                'last_scan' => $last?->format('H:i') ?? '-',
            ];
        });

        $comparison = $this->buildComparisonData($dateFrom, $dateTo, $tokoId);

        return response()->json([
            'data' => $data,
            'comparison' => $comparison,
        ]);
    }

    private function parseDate(?string $value): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildComparisonData(?string $dateFrom, ?string $dateTo, int|string|null $tokoId = null): array
    {
        $resiQuery = Resi::query();
        TokoFilter::apply($resiQuery, $tokoId, 'resis.toko_id');
        if ($dateFrom) {
            $resiQuery->whereDate('tanggal_upload', '>=', $dateFrom);
        }
        if ($dateTo) {
            $resiQuery->whereDate('tanggal_upload', '<=', $dateTo);
        }

        $activeResiQuery = (clone $resiQuery)->where(function ($q) {
            $q->whereNull('status')
                ->orWhere('status', '!=', 'canceled');
        });
        $canceledTotal = (clone $resiQuery)->where('status', 'canceled')->count();

        $importTotal = (clone $activeResiQuery)->count();

        $scannedTotal = 0;
        if ($importTotal > 0) {
            $scannedTotal = PackerScanOut::query()
                ->whereIn('resi_id', (clone $activeResiQuery)->select('id'))
                ->distinct('resi_id')
                ->count('resi_id');
        }

        $missingBase = (clone $activeResiQuery)
            ->leftJoin('packer_scan_outs as ps', 'ps.resi_id', '=', 'resis.id')
            ->whereNull('ps.id')
            ->leftJoin('tokos as t', 't.id', '=', 'resis.toko_id')
            ->leftJoin('channels as c', 'c.id', '=', 'resis.channel_id')
            ->select('resis.id_pesanan', 'resis.no_resi', 'resis.tanggal_upload', 't.name as toko_name', 'c.name as channel_name');

        $missingTotal = (clone $missingBase)->count();
        $missingSamples = (clone $missingBase)
            ->orderByDesc('resis.tanggal_upload')
            ->limit(50)
            ->get()
            ->map(function ($row) {
                return [
                    'id_pesanan' => (string) ($row->id_pesanan ?? '-'),
                    'no_resi' => (string) ($row->no_resi ?? '-'),
                    'toko' => (string) ($row->toko_name ?? '-'),
                    'channel' => (string) ($row->channel_name ?? '-'),
                    'tanggal_upload' => $row->tanggal_upload
                        ? Carbon::parse($row->tanggal_upload)->format('Y-m-d')
                        : '-',
                ];
            });

        return [
            'import_total' => $importTotal,
            'scanned_total' => $scannedTotal,
            'missing_total' => $missingTotal,
            'canceled_total' => $canceledTotal,
            'missing_samples' => $missingSamples,
            'per_toko' => $this->perTokoBreakdown($resiQuery),
        ];
    }

    /**
     * Rekap import vs scan out per toko, diurutkan dari sisa terbanyak.
     */
    private function perTokoBreakdown($resiQuery): array
    {
        $scanned = DB::table('packer_scan_outs')->select('resi_id')->distinct();
        $active = "(resis.status IS NULL OR resis.status != 'canceled')";

        return (clone $resiQuery)
            ->toBase()
            ->leftJoin('tokos as t', 't.id', '=', 'resis.toko_id')
            ->leftJoinSub($scanned, 'pso', 'pso.resi_id', '=', 'resis.id')
            ->groupBy('resis.toko_id', 't.name')
            ->select('resis.toko_id', 't.name as toko_name')
            ->selectRaw("SUM(CASE WHEN {$active} THEN 1 ELSE 0 END) as import_total")
            ->selectRaw("SUM(CASE WHEN {$active} AND pso.resi_id IS NOT NULL THEN 1 ELSE 0 END) as scanned_total")
            ->selectRaw("SUM(CASE WHEN resis.status = 'canceled' THEN 1 ELSE 0 END) as canceled_total")
            ->get()
            ->map(function ($row) {
                $import = (int) $row->import_total;
                $scanned = (int) $row->scanned_total;

                return [
                    'toko' => $row->toko_id ? ($row->toko_name ?? 'Toko #'.$row->toko_id) : 'Tanpa Toko',
                    'import_total' => $import,
                    'scanned_total' => $scanned,
                    'missing_total' => max(0, $import - $scanned),
                    'canceled_total' => (int) $row->canceled_total,
                    'completion_rate' => $import > 0 ? round($scanned / $import * 100, 1) : 0,
                ];
            })
            ->sortBy([['missing_total', 'desc'], ['import_total', 'desc'], ['toko', 'asc']])
            ->values()
            ->all();
    }
}
