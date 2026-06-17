<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsReportController extends Controller
{
    public function stockMutations()
    {
        return view('admin.reports.stock-mutations.index', [
            'dataUrl' => route('admin.reports.stock-mutations.data'),
            'today' => now()->toDateString(),
        ]);
    }

    public function stockMutationsData(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $base = DB::table('stock_mutations as sm')
            ->leftJoin('items as i', 'i.id', '=', 'sm.item_id')
            ->whereBetween('sm.occurred_at', [$from, $to]);

        $sourceType = trim((string) $request->input('source_type', ''));
        if ($sourceType !== '') {
            $base->where('sm.source_type', $sourceType);
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                $q->where('i.sku', 'like', "%{$search}%")
                    ->orWhere('i.name', 'like', "%{$search}%")
                    ->orWhere('sm.source_code', 'like', "%{$search}%")
                    ->orWhere('sm.source_type', 'like', "%{$search}%")
                    ->orWhere('sm.source_subtype', 'like', "%{$search}%");
            });
        }

        $summaryBase = clone $base;
        $summary = $summaryBase
            ->selectRaw('COUNT(*) as total_mutations')
            ->selectRaw('COUNT(DISTINCT sm.item_id) as total_sku')
            ->selectRaw("COALESCE(SUM(CASE WHEN sm.direction = 'in' THEN sm.qty ELSE 0 END), 0) as total_in")
            ->selectRaw("COALESCE(SUM(CASE WHEN sm.direction = 'out' THEN sm.qty ELSE 0 END), 0) as total_out")
            ->first();

        $rowsBase = clone $base;
        $recordsTotal = (clone $rowsBase)
            ->selectRaw("COALESCE(sm.source_type, '-') as source_type")
            ->selectRaw("COALESCE(sm.source_subtype, '-') as source_subtype")
            ->selectRaw('sm.direction')
            ->groupBy('sm.source_type', 'sm.source_subtype', 'sm.direction')
            ->get()
            ->count();

        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        if (! in_array($length, [10, 25, 50, 100], true)) {
            $length = 10;
        }

        $dataQuery = clone $base;
        $dataQuery
            ->selectRaw("COALESCE(sm.source_type, '-') as source_type")
            ->selectRaw("COALESCE(sm.source_subtype, '-') as source_subtype")
            ->selectRaw('sm.direction')
            ->selectRaw('COUNT(*) as mutation_count')
            ->selectRaw('COUNT(DISTINCT sm.item_id) as sku_count')
            ->selectRaw('COALESCE(SUM(sm.qty), 0) as total_qty')
            ->selectRaw('MIN(sm.occurred_at) as first_at')
            ->selectRaw('MAX(sm.occurred_at) as last_at')
            ->groupBy('sm.source_type', 'sm.source_subtype', 'sm.direction')
            ->orderByDesc('total_qty')
            ->orderBy('source_type');

        if ($length > 0) {
            $dataQuery->skip($start)->take($length);
        }

        $data = $dataQuery->get()->map(fn ($row) => [
            'source_type' => strtoupper((string) $row->source_type),
            'source_subtype' => $row->source_subtype ?: '-',
            'direction' => strtoupper((string) $row->direction),
            'mutation_count' => (int) $row->mutation_count,
            'sku_count' => (int) $row->sku_count,
            'total_qty' => (int) $row->total_qty,
            'first_at' => $row->first_at ? Carbon::parse($row->first_at)->format('Y-m-d H:i') : '-',
            'last_at' => $row->last_at ? Carbon::parse($row->last_at)->format('Y-m-d H:i') : '-',
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
            'summary' => [
                'total_mutations' => (int) ($summary->total_mutations ?? 0),
                'total_sku' => (int) ($summary->total_sku ?? 0),
                'total_in' => (int) ($summary->total_in ?? 0),
                'total_out' => (int) ($summary->total_out ?? 0),
                'net_qty' => (int) (($summary->total_in ?? 0) - ($summary->total_out ?? 0)),
            ],
            'data' => $data,
        ]);
    }

    public function couriers()
    {
        return view('admin.reports.couriers.index', [
            'dataUrl' => route('admin.reports.couriers.data'),
            'today' => now()->toDateString(),
        ]);
    }

    public function couriersData(Request $request)
    {
        [$from, $to] = $this->dateRange($request, 'date');
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $resiAgg = DB::table('resis')
            ->select('kurir_id')
            ->selectRaw("COUNT(CASE WHEN status IS NULL OR status != 'canceled' THEN 1 END) as total_resi")
            ->selectRaw("COUNT(CASE WHEN status = 'canceled' THEN 1 END) as canceled_total")
            ->whereBetween('tanggal_upload', [$fromDate, $toDate])
            ->groupBy('kurir_id');

        $scanAgg = DB::table('packer_scan_outs as pso')
            ->join('resis as r', 'r.id', '=', 'pso.resi_id')
            ->select('r.kurir_id')
            ->selectRaw('COUNT(DISTINCT pso.resi_id) as scan_total')
            ->selectRaw('MIN(pso.scanned_at) as first_scan_at')
            ->selectRaw('MAX(pso.scanned_at) as last_scan_at')
            ->whereBetween('r.tanggal_upload', [$fromDate, $toDate])
            ->groupBy('r.kurir_id');

        $base = DB::table('kurirs as k')
            ->leftJoinSub($resiAgg, 'ra', 'ra.kurir_id', '=', 'k.id')
            ->leftJoinSub($scanAgg, 'sa', 'sa.kurir_id', '=', 'k.id')
            ->whereRaw('COALESCE(ra.total_resi, 0) + COALESCE(ra.canceled_total, 0) + COALESCE(sa.scan_total, 0) > 0');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $base->where('k.name', 'like', "%{$search}%");
        }

        $recordsTotal = (clone $base)->count();
        $summaryRows = (clone $base)
            ->selectRaw('COALESCE(SUM(ra.total_resi), 0) as total_resi')
            ->selectRaw('COALESCE(SUM(ra.canceled_total), 0) as canceled_total')
            ->selectRaw('COALESCE(SUM(sa.scan_total), 0) as scan_total')
            ->first();

        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        if (! in_array($length, [10, 25, 50, 100], true)) {
            $length = 10;
        }

        $query = clone $base;
        $query->select([
            'k.id',
            'k.name',
            DB::raw('COALESCE(ra.total_resi, 0) as total_resi'),
            DB::raw('COALESCE(ra.canceled_total, 0) as canceled_total'),
            DB::raw('COALESCE(sa.scan_total, 0) as scan_total'),
            DB::raw('sa.first_scan_at'),
            DB::raw('sa.last_scan_at'),
        ])->orderByDesc(DB::raw('COALESCE(ra.total_resi, 0)'));

        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $total = (int) $row->total_resi;
            $scan = (int) $row->scan_total;
            $remaining = max(0, $total - $scan);

            return [
                'id' => (int) $row->id,
                'name' => $row->name,
                'total_resi' => $total,
                'scan_total' => $scan,
                'remaining' => $remaining,
                'canceled_total' => (int) $row->canceled_total,
                'completion_rate' => $total > 0 ? round(($scan / $total) * 100, 2) : 0,
                'first_scan_at' => $row->first_scan_at ? Carbon::parse($row->first_scan_at)->format('Y-m-d H:i') : '-',
                'last_scan_at' => $row->last_scan_at ? Carbon::parse($row->last_scan_at)->format('Y-m-d H:i') : '-',
            ];
        });

        $totalResi = (int) ($summaryRows->total_resi ?? 0);
        $totalScan = (int) ($summaryRows->scan_total ?? 0);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
            'summary' => [
                'total_resi' => $totalResi,
                'scan_total' => $totalScan,
                'remaining' => max(0, $totalResi - $totalScan),
                'canceled_total' => (int) ($summaryRows->canceled_total ?? 0),
                'completion_rate' => $totalResi > 0 ? round(($totalScan / $totalResi) * 100, 2) : 0,
            ],
            'data' => $data,
        ]);
    }

    public function stockHealth()
    {
        return view('admin.reports.stock-health.index', [
            'dataUrl' => route('admin.reports.stock-health.data'),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function stockHealthData(Request $request)
    {
        $movementAgg = DB::table('stock_mutations')
            ->select('item_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN qty ELSE 0 END), 0) as inbound_qty")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' THEN qty ELSE 0 END), 0) as outbound_qty")
            ->selectRaw('MAX(occurred_at) as last_mutation_at')
            ->groupBy('item_id');

        $base = DB::table('items as i')
            ->leftJoin('item_stocks as s', 's.item_id', '=', 'i.id')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->leftJoinSub($movementAgg, 'ma', 'ma.item_id', '=', 'i.id')
            ->where('i.is_active', true);

        $categoryId = $request->input('category_id');
        if ($categoryId !== null && $categoryId !== '') {
            if ((int) $categoryId === 0) {
                $base->where('i.category_id', 0);
            } else {
                $base->where('i.category_id', (int) $categoryId);
            }
        }

        $status = $request->input('status');
        if ($status === 'out') {
            $base->whereRaw('COALESCE(s.stock, 0) <= 0');
        } elseif ($status === 'low') {
            $base->where('i.safety_stock', '>', 0)
                ->whereRaw('COALESCE(s.stock, 0) > 0')
                ->whereRaw('COALESCE(s.stock, 0) < i.safety_stock');
        } elseif ($status === 'healthy') {
            $base->where(function ($q) {
                $q->whereRaw('i.safety_stock <= 0')
                    ->orWhereRaw('COALESCE(s.stock, 0) >= i.safety_stock');
            })->whereRaw('COALESCE(s.stock, 0) > 0');
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                $q->where('i.sku', 'like', "%{$search}%")
                    ->orWhere('i.name', 'like', "%{$search}%")
                    ->orWhere('i.address', 'like', "%{$search}%");
            });
        }

        $recordsTotal = (clone $base)->count();
        $summary = (clone $base)
            ->selectRaw('COUNT(*) as total_sku')
            ->selectRaw('COALESCE(SUM(COALESCE(s.stock, 0)), 0) as total_stock')
            ->selectRaw('COUNT(CASE WHEN COALESCE(s.stock, 0) <= 0 THEN 1 END) as out_of_stock')
            ->selectRaw('COUNT(CASE WHEN i.safety_stock > 0 AND COALESCE(s.stock, 0) > 0 AND COALESCE(s.stock, 0) < i.safety_stock THEN 1 END) as low_stock')
            ->selectRaw('COUNT(CASE WHEN i.safety_stock > 0 AND COALESCE(s.stock, 0) >= i.safety_stock THEN 1 END) as healthy_stock')
            ->selectRaw('COUNT(CASE WHEN i.safety_stock <= 0 THEN 1 END) as no_safety_stock')
            ->first();

        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        if (! in_array($length, [10, 25, 50, 100], true)) {
            $length = 10;
        }

        $query = clone $base;
        $query->select([
            'i.id',
            'i.sku',
            'i.name',
            'i.address',
            'i.safety_stock',
            DB::raw("CASE WHEN i.category_id = 0 THEN 'Tanpa Kategori' ELSE COALESCE(c.name, '-') END as category"),
            DB::raw('COALESCE(s.stock, 0) as stock'),
            DB::raw('COALESCE(ma.inbound_qty, 0) as inbound_qty'),
            DB::raw('COALESCE(ma.outbound_qty, 0) as outbound_qty'),
            DB::raw('ma.last_mutation_at'),
        ])->orderByRaw('CASE WHEN COALESCE(s.stock, 0) <= 0 THEN 0 WHEN i.safety_stock > 0 AND COALESCE(s.stock, 0) < i.safety_stock THEN 1 ELSE 2 END')
            ->orderBy('i.sku');

        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $stock = (int) $row->stock;
            $safety = (int) $row->safety_stock;
            $status = 'Healthy';
            if ($stock <= 0) {
                $status = 'Out of Stock';
            } elseif ($safety > 0 && $stock < $safety) {
                $status = 'Low Stock';
            } elseif ($safety <= 0) {
                $status = 'No Safety Stock';
            }

            return [
                'id' => (int) $row->id,
                'sku' => $row->sku ?? '-',
                'name' => $row->name ?? '-',
                'category' => $row->category ?? '-',
                'address' => $row->address ?? '-',
                'stock' => $stock,
                'safety_stock' => $safety,
                'gap' => max(0, $safety - $stock),
                'inbound_qty' => (int) $row->inbound_qty,
                'outbound_qty' => (int) $row->outbound_qty,
                'net_qty' => (int) $row->inbound_qty - (int) $row->outbound_qty,
                'last_mutation_at' => $row->last_mutation_at ? Carbon::parse($row->last_mutation_at)->format('Y-m-d H:i') : '-',
                'status' => $status,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
            'summary' => [
                'total_sku' => (int) ($summary->total_sku ?? 0),
                'total_stock' => (int) ($summary->total_stock ?? 0),
                'out_of_stock' => (int) ($summary->out_of_stock ?? 0),
                'low_stock' => (int) ($summary->low_stock ?? 0),
                'healthy_stock' => (int) ($summary->healthy_stock ?? 0),
                'no_safety_stock' => (int) ($summary->no_safety_stock ?? 0),
            ],
            'data' => $data,
        ]);
    }

    private function dateRange(Request $request, string $mode = 'datetime'): array
    {
        $today = now()->toDateString();
        $dateFrom = $request->input('date_from') ?: $today;
        $dateTo = $request->input('date_to') ?: $today;

        try {
            $from = Carbon::parse($dateFrom);
            $to = Carbon::parse($dateTo);
        } catch (\Throwable) {
            $from = Carbon::parse($today);
            $to = Carbon::parse($today);
        }

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return $mode === 'date'
            ? [$from->startOfDay(), $to->endOfDay()]
            : [$from->startOfDay(), $to->endOfDay()];
    }
}
