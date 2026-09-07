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

        $resiBase = Resi::query()->whereDate('tanggal_upload', $selectedDate);
        $activeResiBase = (clone $resiBase)->where(function ($q) {
            $q->whereNull('status')
                ->orWhere('status', '!=', 'canceled');
        });

        $totalResiActive = (clone $activeResiBase)->count();
        $totalResiCanceled = (clone $resiBase)->where('status', 'canceled')->count();
        $totalResiUpdatedAt = (clone $activeResiBase)->max('updated_at');
        $totalScanOut = PackerScanOut::query()
            ->whereIn('resi_id', (clone $resiBase)->select('id'))
            ->count();
        $totalScanUpdatedAt = PackerScanOut::query()
            ->whereIn('resi_id', (clone $resiBase)->select('id'))
            ->max('scanned_at');
        $totalQcScan = QcScanResi::query()
            ->whereIn('resi_id', (clone $resiBase)->select('id'))
            ->count();
        $totalQcCompleted = QcScanResi::query()
            ->whereIn('resi_id', (clone $resiBase)->select('id'))
            ->where('status', 'completed')
            ->count();
        $totalQcUpdatedAt = QcScanResi::query()
            ->whereIn('resi_id', (clone $resiBase)->select('id'))
            ->selectRaw('MAX(COALESCE(completed_at, scanned_at)) as latest_at')
            ->value('latest_at');
        $totalResiUpdated = $totalResiUpdatedAt ? Carbon::parse($totalResiUpdatedAt)->format('H:i') : '-';
        $totalScanUpdated = $totalScanUpdatedAt ? Carbon::parse($totalScanUpdatedAt)->format('H:i') : '-';
        $totalQcUpdated = $totalQcUpdatedAt ? Carbon::parse($totalQcUpdatedAt)->format('H:i') : '-';

        $selectedStart = Carbon::parse($selectedDate)->startOfDay();
        $selectedEnd = Carbon::parse($selectedDate)->endOfDay();
        $movementStart = Carbon::parse($selectedDate)->subDays(29)->startOfDay();

        $inventorySummary = DB::table('items as i')
            ->leftJoin('item_stocks as s', 's.item_id', '=', 'i.id')
            ->where('i.is_active', true)
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

        $resiCounts = Resi::select('kurir_id', DB::raw('count(*) as total'))
            ->whereDate('tanggal_upload', $selectedDate)
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhere('status', '!=', 'canceled');
            })
            ->groupBy('kurir_id')
            ->pluck('total', 'kurir_id')
            ->toArray();

        $canceledCounts = Resi::select('kurir_id', DB::raw('count(*) as total'))
            ->whereDate('tanggal_upload', $selectedDate)
            ->where('status', 'canceled')
            ->groupBy('kurir_id')
            ->pluck('total', 'kurir_id')
            ->toArray();

        $scanCounts = PackerScanOut::query()
            ->join('resis', 'resis.id', '=', 'packer_scan_outs.resi_id')
            ->select('resis.kurir_id', DB::raw('count(*) as total'))
            ->whereDate('resis.tanggal_upload', $selectedDate)
            ->groupBy('resis.kurir_id')
            ->pluck('total', 'resis.kurir_id')
            ->toArray();

        $resiLatest = Resi::select('kurir_id', DB::raw('max(updated_at) as latest'))
            ->whereDate('tanggal_upload', $selectedDate)
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhere('status', '!=', 'canceled');
            })
            ->groupBy('kurir_id')
            ->pluck('latest', 'kurir_id')
            ->toArray();

        $scanLatest = PackerScanOut::query()
            ->join('resis', 'resis.id', '=', 'packer_scan_outs.resi_id')
            ->select('resis.kurir_id', DB::raw('max(packer_scan_outs.scanned_at) as latest'))
            ->whereDate('resis.tanggal_upload', $selectedDate)
            ->groupBy('resis.kurir_id')
            ->pluck('latest', 'resis.kurir_id')
            ->toArray();

        $kurirs = Kurir::orderBy('name')
            ->get(['id', 'name'])
            ->map(function ($kurir) use ($resiCounts, $canceledCounts, $scanCounts, $resiLatest, $scanLatest) {
                $resiTotal = (int) ($resiCounts[$kurir->id] ?? 0);
                $scanTotal = (int) ($scanCounts[$kurir->id] ?? 0);
                $canceledTotal = (int) ($canceledCounts[$kurir->id] ?? 0);
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
                    'canceled_total' => $canceledTotal,
                    'last_update' => $latestTime,
                ];
            });

        return view('admin.dashboard', [
            'report' => $report,
            'reportFilters' => $reportFilters,
            'today' => $selectedDate,
            'totalResi' => $totalResiActive,
            'totalResiCanceled' => $totalResiCanceled,
            'totalScanOut' => $totalScanOut,
            'totalQcScan' => $totalQcScan,
            'totalQcCompleted' => $totalQcCompleted,
            'totalResiUpdated' => $totalResiUpdated,
            'totalScanUpdated' => $totalScanUpdated,
            'totalQcUpdated' => $totalQcUpdated,
            'inventorySummary' => $inventorySummary,
            'todayMovement' => $todayMovement,
            'topOutgoingItems' => $topOutgoingItems,
            'outOfStockItems' => $outOfStockItems,
            'lowStockItems' => $lowStockItems,
            'pendingApprovals' => $pendingApprovals,
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
