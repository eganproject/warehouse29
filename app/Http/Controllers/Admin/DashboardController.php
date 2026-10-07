<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kurir;
use App\Models\PackerScanOut;
use App\Models\QcScanResi;
use App\Models\Resi;
use App\Support\ResiReport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $reportFilters = $request->validate([
            'report_start' => ['nullable', 'date_format:Y-m-d'],
            'report_end' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $reportFilters['report_start'] = $reportFilters['report_start'] ?? Carbon::parse($reportFilters['report_end'] ?? now())->startOfMonth()->toDateString();
        $reportFilters['report_end'] = $reportFilters['report_end'] ?? max($reportFilters['report_start'], now()->toDateString());
        validator($reportFilters, [
            'report_end' => ['after_or_equal:report_start', 'before_or_equal:'.Carbon::parse($reportFilters['report_start'])->addDays(365)->toDateString()],
        ], ['report_end.before_or_equal' => 'Rentang laporan maksimal 366 hari.'])->validate();
        $report = app(ResiReport::class)->build($reportFilters);

        $today = now()->toDateString();
        $selectedDate = $today;
        $dateInput = $request->query('date');
        if ($dateInput) {
            try {
                $selectedDate = Carbon::parse($dateInput)->toDateString();
            } catch (\Throwable) {
                $selectedDate = $today;
            }
        }

        $resiOverview = $this->resiOverview($selectedDate);

        $selectedStart = Carbon::parse($selectedDate)->startOfDay();
        $selectedEnd = Carbon::parse($selectedDate)->endOfDay();
        $movementStart = Carbon::parse($selectedDate)->subDays(29)->startOfDay();

        $inventorySummary = DB::table('items as i')
            ->leftJoin('item_stocks as s', 's.item_id', '=', 'i.id')
            ->where('i.is_active', true)
            // Bundle hanya stok virtual; stok fisiknya ada di komponen.
            ->where('i.is_bundle', false)
            ->selectRaw('COUNT(*) as total_sku')
            ->selectRaw('COALESCE(SUM(COALESCE(s.stock, 0)), 0) as total_stock')
            ->selectRaw('COUNT(CASE WHEN COALESCE(s.stock, 0) <= 0 THEN 1 END) as out_of_stock')
            ->selectRaw('COUNT(CASE WHEN i.safety_stock > 0 AND COALESCE(s.stock, 0) > 0 AND COALESCE(s.stock, 0) < i.safety_stock THEN 1 END) as low_stock')
            ->selectRaw('COUNT(CASE WHEN i.safety_stock <= 0 THEN 1 END) as no_safety_stock')
            ->first();

        $todayMovement = DB::table('stock_mutations')
            ->whereBetween('occurred_at', [$selectedStart, $selectedEnd])
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN qty ELSE 0 END), 0) as stock_in")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' THEN qty ELSE 0 END), 0) as stock_out")
            ->selectRaw("COUNT(DISTINCT CASE WHEN direction = 'in' THEN item_id END) as sku_in")
            ->selectRaw("COUNT(DISTINCT CASE WHEN direction = 'out' THEN item_id END) as sku_out")
            ->first();

        $topOutgoingItems = DB::table('stock_mutations as sm')
            ->join('items as i', 'i.id', '=', 'sm.item_id')
            ->where('sm.direction', 'out')
            ->whereBetween('sm.occurred_at', [$movementStart, $selectedEnd])
            ->groupBy('i.id', 'i.sku', 'i.name')
            ->select([
                'i.sku',
                'i.name',
                DB::raw('COALESCE(SUM(sm.qty), 0) as total_qty'),
                DB::raw('COUNT(*) as mutation_count'),
            ])
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        $outOfStockItems = DB::table('items as i')
            ->leftJoin('item_stocks as s', 's.item_id', '=', 'i.id')
            ->where('i.is_active', true)
            // Bundle hanya stok virtual; stok fisiknya ada di komponen.
            ->where('i.is_bundle', false)
            ->whereRaw('COALESCE(s.stock, 0) <= 0')
            ->select([
                'i.sku',
                'i.name',
                'i.address',
                'i.safety_stock',
                DB::raw('COALESCE(s.stock, 0) as stock'),
            ])
            ->orderBy('i.sku')
            ->limit(8)
            ->get();

        $lowStockItems = DB::table('items as i')
            ->leftJoin('item_stocks as s', 's.item_id', '=', 'i.id')
            ->where('i.is_active', true)
            // Bundle hanya stok virtual; stok fisiknya ada di komponen.
            ->where('i.is_bundle', false)
            ->where('i.safety_stock', '>', 0)
            ->whereRaw('COALESCE(s.stock, 0) > 0')
            ->whereRaw('COALESCE(s.stock, 0) < i.safety_stock')
            ->select([
                'i.sku',
                'i.name',
                'i.address',
                'i.safety_stock',
                DB::raw('COALESCE(s.stock, 0) as stock'),
                DB::raw('(i.safety_stock - COALESCE(s.stock, 0)) as gap'),
            ])
            ->orderByDesc('gap')
            ->orderBy('i.sku')
            ->limit(8)
            ->get();

        $pendingApprovals = [
            'inbound' => DB::table('inbound_transactions')->where('status', 'pending')->count(),
            'outbound' => DB::table('outbound_transactions')->where('status', 'pending')->count(),
            'adjustment' => DB::table('stock_adjustments')->where('status', 'pending')->count(),
            'damaged_goods' => DB::table('damaged_goods')->where('status', 'pending')->count(),
        ];

        return view('admin.dashboard', [
            'report' => $report,
            'reportFilters' => $reportFilters,
            'today' => $selectedDate,
            'resiSummary' => $resiOverview['summary'],
            'kurirs' => $resiOverview['kurirs'],
            'inventorySummary' => $inventorySummary,
            'todayMovement' => $todayMovement,
            'topOutgoingItems' => $topOutgoingItems,
            'outOfStockItems' => $outOfStockItems,
            'lowStockItems' => $lowStockItems,
            'pendingApprovals' => $pendingApprovals,
        ]);
    }

    /**
     * Resi pipeline for one upload date, grouped per kurir. Only kurirs that have
     * resis on that date are returned; QC and scan out are counted for active resis only.
     */
    private function resiOverview(string $date): array
    {
        $active = "(r.status IS NULL OR r.status != 'canceled')";

        $rows = DB::table('resis as r')
            ->leftJoin('kurirs as k', 'k.id', '=', 'r.kurir_id')
            ->leftJoin('qc_scan_resis as qs', 'qs.resi_id', '=', 'r.id')
            ->leftJoin('packer_scan_outs as pso', 'pso.resi_id', '=', 'r.id')
            ->whereDate('r.tanggal_upload', $date)
            ->groupBy('r.kurir_id', 'k.name')
            ->select('r.kurir_id', 'k.name')
            ->selectRaw("SUM(CASE WHEN {$active} THEN 1 ELSE 0 END) as active_total")
            ->selectRaw("SUM(CASE WHEN r.status = 'canceled' THEN 1 ELSE 0 END) as canceled_total")
            ->selectRaw("SUM(CASE WHEN {$active} AND qs.id IS NOT NULL THEN 1 ELSE 0 END) as qc_total")
            ->selectRaw("SUM(CASE WHEN {$active} AND qs.status = 'completed' THEN 1 ELSE 0 END) as qc_completed")
            ->selectRaw("SUM(CASE WHEN {$active} AND pso.id IS NOT NULL THEN 1 ELSE 0 END) as scan_total")
            ->selectRaw("SUM(CASE WHEN {$active} AND pso.id IS NULL AND qs.id IS NOT NULL THEN 1 ELSE 0 END) as waiting_scan")
            ->selectRaw('MAX(r.updated_at) as resi_latest')
            ->selectRaw('MAX(COALESCE(qs.completed_at, qs.scanned_at)) as qc_latest')
            ->selectRaw('MAX(pso.scanned_at) as scan_latest')
            ->get();

        $latestOf = function (...$values) {
            $values = array_filter($values);

            return $values ? max(array_map(fn ($value) => Carbon::parse($value), $values)) : null;
        };

        $kurirs = $rows->map(function ($row) use ($latestOf) {
            $activeTotal = (int) $row->active_total;
            $scanTotal = (int) $row->scan_total;
            $latest = $latestOf($row->resi_latest, $row->qc_latest, $row->scan_latest);

            return [
                'id' => $row->kurir_id ? (int) $row->kurir_id : null,
                'name' => $row->kurir_id ? ($row->name ?? 'Kurir #'.$row->kurir_id) : 'Tanpa Kurir',
                'resi_total' => $activeTotal,
                'qc_total' => (int) $row->qc_total,
                'qc_completed' => (int) $row->qc_completed,
                'scan_total' => $scanTotal,
                'waiting_scan' => (int) $row->waiting_scan,
                'remaining' => max(0, $activeTotal - $scanTotal),
                'canceled_total' => (int) $row->canceled_total,
                'progress' => $activeTotal > 0 ? (int) floor($scanTotal / $activeTotal * 100) : 0,
                'latest_at' => $latest,
                'last_update' => $latest ? $latest->format('H:i') : '-',
            ];
        })
            ->sortBy([
                ['remaining', 'desc'],
                ['resi_total', 'desc'],
                ['name', 'asc'],
            ])
            ->values();

        $activeTotal = (int) $kurirs->sum('resi_total');
        $scanTotal = (int) $kurirs->sum('scan_total');
        $waitingScan = (int) $kurirs->sum('waiting_scan');
        $latest = $kurirs->pluck('latest_at')->filter()->max();

        return [
            'summary' => (object) [
                'active' => $activeTotal,
                'canceled' => (int) $kurirs->sum('canceled_total'),
                'qc' => (int) $kurirs->sum('qc_total'),
                'qc_completed' => (int) $kurirs->sum('qc_completed'),
                'scan' => $scanTotal,
                'remaining' => max(0, $activeTotal - $scanTotal),
                'waiting_scan' => $waitingScan,
                'not_started' => max(0, $activeTotal - $scanTotal - $waitingScan),
                'kurir_count' => $kurirs->count(),
                'kurir_done' => $kurirs->filter(fn ($k) => $k['resi_total'] > 0 && $k['remaining'] === 0)->count(),
                'last_update' => $latest ? $latest->format('H:i') : '-',
            ],
            'kurirs' => $kurirs,
        ];
    }

    public function kurirDetail(Request $request)
    {
        $validated = $request->validate([
            'kurir_id' => ['required', 'integer', 'exists:kurirs,id'],
            'date' => ['nullable', 'date'],
        ]);

        $date = Carbon::parse($validated['date'] ?? now())->toDateString();
        $kurir = Kurir::query()->findOrFail((int) $validated['kurir_id'], ['id', 'name']);

        $resis = Resi::query()
            ->with('details:id,resi_id,sku,qty')
            ->where('kurir_id', $kurir->id)
            ->whereDate('tanggal_upload', $date)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['id', 'id_pesanan', 'no_resi', 'tanggal_upload', 'status']);

        $scannedResiIds = PackerScanOut::query()
            ->whereIn('resi_id', $resis->pluck('id'))
            ->pluck('resi_id')
            ->flip();

        $data = $resis->map(function ($resi) use ($scannedResiIds) {
            $isCanceled = ($resi->status ?? 'active') === 'canceled';
            $isScanned = $scannedResiIds->has($resi->id);

            if ($isCanceled) {
                $statusKey = 'canceled';
                $statusLabel = 'Dibatalkan';
            } elseif ($isScanned) {
                $statusKey = 'scanned';
                $statusLabel = 'Sudah Scan Out';
            } else {
                $statusKey = 'pending';
                $statusLabel = 'Belum Scan Out';
            }

            $items = $resi->details
                ->map(fn ($detail) => [
                    'sku' => $detail->sku ?: '-',
                    'qty' => (int) $detail->qty,
                ])
                ->values();

            return [
                'id_pesanan' => $resi->id_pesanan ?? '-',
                'no_resi' => $resi->no_resi ?? '-',
                'status_key' => $statusKey,
                'status_label' => $statusLabel,
                'tanggal_upload' => $resi->tanggal_upload
                    ? Carbon::parse($resi->tanggal_upload)->format('Y-m-d')
                    : '-',
                'items' => $items,
                'total_qty' => (int) $items->sum('qty'),
            ];
        })->values();

        $scannedTotal = $data->where('status_key', 'scanned')->count();
        $pendingTotal = $data->where('status_key', 'pending')->count();
        $canceledTotal = $data->where('status_key', 'canceled')->count();

        return response()->json([
            'meta' => [
                'kurir_name' => $kurir->name,
                'date' => $date,
                'total_resi' => $scannedTotal + $pendingTotal,
                'scanned_total' => $scannedTotal,
                'remaining_total' => $pendingTotal,
                'canceled_total' => $canceledTotal,
            ],
            'data' => $data,
        ]);
    }
}
