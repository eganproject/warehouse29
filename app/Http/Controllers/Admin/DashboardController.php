<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kurir;
use App\Models\PackerScanOut;
use App\Models\Resi;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();

        $totalResi = Resi::whereDate('tanggal_upload', $today)->count();
        $totalScanOut = PackerScanOut::whereDate('scan_date', $today)->count();
        $totalResiUpdatedAt = Resi::whereDate('tanggal_upload', $today)->max('updated_at');
        $totalScanUpdatedAt = PackerScanOut::whereDate('scan_date', $today)->max('scanned_at');
        $totalResiUpdated = $totalResiUpdatedAt ? Carbon::parse($totalResiUpdatedAt)->format('H:i') : '-';
        $totalScanUpdated = $totalScanUpdatedAt ? Carbon::parse($totalScanUpdatedAt)->format('H:i') : '-';

        $resiCounts = Resi::select('kurir_id', DB::raw('count(*) as total'))
            ->whereDate('tanggal_upload', $today)
            ->groupBy('kurir_id')
            ->pluck('total', 'kurir_id')
            ->toArray();

        $scanCounts = PackerScanOut::select('kurir_id', DB::raw('count(*) as total'))
            ->whereDate('scan_date', $today)
            ->groupBy('kurir_id')
            ->pluck('total', 'kurir_id')
            ->toArray();

        $resiLatest = Resi::select('kurir_id', DB::raw('max(updated_at) as latest'))
            ->whereDate('tanggal_upload', $today)
            ->groupBy('kurir_id')
            ->pluck('latest', 'kurir_id')
            ->toArray();

        $scanLatest = PackerScanOut::select('kurir_id', DB::raw('max(scanned_at) as latest'))
            ->whereDate('scan_date', $today)
            ->groupBy('kurir_id')
            ->pluck('latest', 'kurir_id')
            ->toArray();

        $kurirs = Kurir::orderBy('name')
            ->get(['id', 'name'])
            ->map(function ($kurir) use ($resiCounts, $scanCounts, $resiLatest, $scanLatest) {
                $resiTotal = (int) ($resiCounts[$kurir->id] ?? 0);
                $scanTotal = (int) ($scanCounts[$kurir->id] ?? 0);
                $latestResi = $resiLatest[$kurir->id] ?? null;
                $latestScan = $scanLatest[$kurir->id] ?? null;
                $latestRaw = $latestResi && $latestScan
                    ? (Carbon::parse($latestResi)->greaterThan(Carbon::parse($latestScan)) ? $latestResi : $latestScan)
                    : ($latestResi ?: $latestScan);
                $latestTime = $latestRaw ? Carbon::parse($latestRaw)->format('H:i') : '-';
                return [
                    'id' => $kurir->id,
                    'name' => $kurir->name,
                    'resi_total' => $resiTotal,
                    'scan_total' => $scanTotal,
                    'remaining' => max(0, $resiTotal - $scanTotal),
                    'last_update' => $latestTime,
                ];
            });

        return view('admin.dashboard', [
            'today' => $today,
            'totalResi' => $totalResi,
            'totalScanOut' => $totalScanOut,
            'totalResiUpdated' => $totalResiUpdated,
            'totalScanUpdated' => $totalScanUpdated,
            'kurirs' => $kurirs,
        ]);
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
            ->where('kurir_id', $kurir->id)
            ->whereDate('tanggal_upload', $date)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['id', 'id_pesanan', 'no_resi', 'tanggal_upload', 'status']);

        $scanOuts = PackerScanOut::query()
            ->with('scanner:id,name')
            ->whereDate('scan_date', $date)
            ->whereIn('resi_id', $resis->pluck('id'))
            ->orderByDesc('scanned_at')
            ->get(['id', 'resi_id', 'scan_type', 'scan_code', 'scanned_at', 'scanned_by'])
            ->unique('resi_id')
            ->keyBy('resi_id');

        $activeResis = $resis->filter(function ($resi) {
            return ($resi->status ?? 'active') !== 'canceled';
        })->values();

        $pendingResis = $activeResis->filter(function ($resi) use ($scanOuts) {
            return !$scanOuts->has($resi->id);
        })->values();

        $data = $pendingResis->map(function ($resi) {
            return [
                'id_pesanan' => $resi->id_pesanan ?? '-',
                'no_resi' => $resi->no_resi ?? '-',
                'status' => 'Belum Scan Out',
                'tanggal_upload' => $resi->tanggal_upload
                    ? Carbon::parse($resi->tanggal_upload)->format('Y-m-d')
                    : '-',
            ];
        })->values();

        return response()->json([
            'meta' => [
                'kurir_name' => $kurir->name,
                'date' => $date,
                'total_resi' => $activeResis->count(),
                'scanned_total' => $activeResis->count() - $pendingResis->count(),
                'remaining_total' => $pendingResis->count(),
                'canceled_total' => $resis->count() - $activeResis->count(),
            ],
            'data' => $data,
        ]);
    }
}
