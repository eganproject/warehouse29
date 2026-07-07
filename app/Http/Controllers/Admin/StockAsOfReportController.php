<?php

namespace App\Http\Controllers\Admin;

use App\Exports\StockAsOfReportExport;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

class StockAsOfReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.stock-as-of.index', [
            'dataUrl' => route('admin.reports.stock-as-of.data'),
            'exportUrl' => route('admin.reports.stock-as-of.export'),
            'today' => now()->toDateString(),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function data(Request $request)
    {
        $asOf = $this->asOfDate($request);
        $base = $this->baseQuery($request, $asOf);

        $recordsFiltered = (clone $base)->count();
        $summary = $this->summary($base);

        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        if (! in_array($length, [10, 25, 50, 100], true)) {
            $length = 10;
        }

        $query = clone $base;
        $query->orderBy('i.sku');
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsFiltered,
            'recordsFiltered' => $recordsFiltered,
            'summary' => $summary,
            'data' => $query->get()->map(fn ($row) => $this->formatRow($row))->values(),
        ]);
    }

    public function export(Request $request)
    {
        $asOf = $this->asOfDate($request);
        $filters = $this->exportFilters($request, $asOf);
        $filename = 'laporan-stok-per-tanggal-'.$asOf->format('Ymd').'-'.now()->format('His').'.xlsx';

        return Excel::download(new StockAsOfReportExport($filters), $filename);
    }

    public function rowsForExport(array $filters)
    {
        $request = new Request($filters);
        $query = $this->baseQuery($request, Carbon::parse($filters['as_of_date'])->endOfDay())
            ->orderBy('i.sku');

        return $query->get()->map(fn ($row) => $this->formatRow($row));
    }

    public function summaryForExport(array $filters): array
    {
        $request = new Request($filters);
        $query = $this->baseQuery($request, Carbon::parse($filters['as_of_date'])->endOfDay());

        return $this->summary($query);
    }

    private function baseQuery(Request $request, Carbon $asOf): Builder
    {
        $hasAddress = Schema::hasColumn('items', 'address');
        $hasSafetyStock = Schema::hasColumn('items', 'safety_stock');
        $hasIsBundle = Schema::hasColumn('items', 'is_bundle');
        $bundleCondition = $hasIsBundle ? 'i.is_bundle = 1' : '0 = 1';

        $regularAgg = DB::table('stock_mutations')
            ->select('item_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN qty ELSE -qty END), 0) as stock_as_of")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN qty ELSE 0 END), 0) as inbound_as_of")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' THEN qty ELSE 0 END), 0) as outbound_as_of")
            ->selectRaw('MAX(occurred_at) as last_mutation_at')
            ->where('occurred_at', '<=', $asOf)
            ->groupBy('item_id');

        $damagedAgg = DB::table('damaged_stock_mutations')
            ->select('item_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN qty ELSE -qty END), 0) as damaged_stock_as_of")
            ->where('occurred_at', '<=', $asOf)
            ->groupBy('item_id');

        $bundleAgg = DB::table('item_bundles as ib')
            ->leftJoinSub($regularAgg, 'component_stock', 'component_stock.item_id', '=', 'ib.component_item_id')
            ->select('ib.bundle_item_id')
            ->selectRaw('COALESCE(MIN(FLOOR(COALESCE(component_stock.stock_as_of, 0) / NULLIF(ib.qty, 0))), 0) as bundle_stock_as_of')
            ->groupBy('ib.bundle_item_id');

        $base = DB::table('items as i')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->leftJoinSub($regularAgg, 'ra', 'ra.item_id', '=', 'i.id')
            ->leftJoinSub($damagedAgg, 'da', 'da.item_id', '=', 'i.id')
            ->leftJoinSub($bundleAgg, 'ba', 'ba.bundle_item_id', '=', 'i.id')
            ->select([
                'i.id',
                'i.sku',
                'i.name',
                DB::raw($hasAddress ? 'i.address' : "'' as address"),
                DB::raw($hasSafetyStock ? 'i.safety_stock' : '0 as safety_stock'),
                DB::raw($hasIsBundle ? 'i.is_bundle' : '0 as is_bundle'),
                DB::raw("CASE WHEN i.category_id = 0 THEN 'Tanpa Kategori' ELSE COALESCE(c.name, '-') END as category"),
                DB::raw("CASE WHEN {$bundleCondition} THEN COALESCE(ba.bundle_stock_as_of, 0) ELSE COALESCE(ra.stock_as_of, 0) END as stock_as_of"),
                DB::raw("CASE WHEN {$bundleCondition} THEN 0 ELSE COALESCE(da.damaged_stock_as_of, 0) END as damaged_stock_as_of"),
                DB::raw('COALESCE(ra.inbound_as_of, 0) as inbound_as_of'),
                DB::raw('COALESCE(ra.outbound_as_of, 0) as outbound_as_of'),
                DB::raw('ra.last_mutation_at'),
            ]);

        if (Schema::hasColumn('items', 'is_active')) {
            $base->where('i.is_active', true);
        }

        $this->applyFilters($base, $request, $bundleCondition, $hasAddress, $hasSafetyStock);

        return $base;
    }

    private function applyFilters(Builder $query, Request $request, string $bundleCondition, bool $hasAddress, bool $hasSafetyStock): void
    {
        $categoryId = $request->input('category_id');
        if ($categoryId !== null && $categoryId !== '') {
            if ((int) $categoryId === 0) {
                $query->where('i.category_id', 0);
            } else {
                $query->where('i.category_id', (int) $categoryId);
            }
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('i.sku', 'like', "%{$search}%")
                    ->orWhere('i.name', 'like', "%{$search}%");

                if (Schema::hasColumn('items', 'address')) {
                    $q->orWhere('i.address', 'like', "%{$search}%");
                }
                if (Schema::hasColumn('items', 'description')) {
                    $q->orWhere('i.description', 'like', "%{$search}%");
                }
            });
        }

        $stockType = $request->input('stock_type', 'regular');
        $stockExpr = $stockType === 'damaged'
            ? "CASE WHEN {$bundleCondition} THEN 0 ELSE COALESCE(da.damaged_stock_as_of, 0) END"
            : "CASE WHEN {$bundleCondition} THEN COALESCE(ba.bundle_stock_as_of, 0) ELSE COALESCE(ra.stock_as_of, 0) END";

        $status = $request->input('status');
        if ($status === 'positive') {
            $query->whereRaw($stockExpr.' > 0');
        } elseif ($status === 'zero') {
            $query->whereRaw($stockExpr.' = 0');
        } elseif ($status === 'negative') {
            $query->whereRaw($stockExpr.' < 0');
        } elseif ($status === 'low' && $stockType !== 'damaged' && $hasSafetyStock) {
            $query->where('i.safety_stock', '>', 0)
                ->whereRaw($stockExpr.' < i.safety_stock');
        }

        if (! $request->boolean('include_zero', true) && ($status === null || $status === '')) {
            $query->whereRaw("(CASE WHEN {$bundleCondition} THEN COALESCE(ba.bundle_stock_as_of, 0) ELSE COALESCE(ra.stock_as_of, 0) END) != 0 OR (CASE WHEN {$bundleCondition} THEN 0 ELSE COALESCE(da.damaged_stock_as_of, 0) END) != 0");
        }
    }

    private function summary(Builder $base): array
    {
        $wrapped = DB::query()->fromSub($base, 'x');
        $row = $wrapped
            ->selectRaw('COUNT(*) as total_sku')
            ->selectRaw('COALESCE(SUM(stock_as_of), 0) as total_regular')
            ->selectRaw('COALESCE(SUM(damaged_stock_as_of), 0) as total_damaged')
            ->selectRaw('COUNT(CASE WHEN stock_as_of > 0 THEN 1 END) as positive_regular_sku')
            ->selectRaw('COUNT(CASE WHEN stock_as_of = 0 THEN 1 END) as zero_regular_sku')
            ->selectRaw('COUNT(CASE WHEN stock_as_of < 0 THEN 1 END) as negative_regular_sku')
            ->selectRaw('COUNT(CASE WHEN safety_stock > 0 AND stock_as_of < safety_stock THEN 1 END) as low_stock_sku')
            ->first();

        return [
            'total_sku' => (int) ($row->total_sku ?? 0),
            'total_regular' => (int) ($row->total_regular ?? 0),
            'total_damaged' => (int) ($row->total_damaged ?? 0),
            'total_all' => (int) (($row->total_regular ?? 0) + ($row->total_damaged ?? 0)),
            'positive_regular_sku' => (int) ($row->positive_regular_sku ?? 0),
            'zero_regular_sku' => (int) ($row->zero_regular_sku ?? 0),
            'negative_regular_sku' => (int) ($row->negative_regular_sku ?? 0),
            'low_stock_sku' => (int) ($row->low_stock_sku ?? 0),
        ];
    }

    private function formatRow(object $row): array
    {
        $regular = (int) $row->stock_as_of;
        $damaged = (int) $row->damaged_stock_as_of;
        $safety = (int) ($row->safety_stock ?? 0);

        return [
            'id' => (int) $row->id,
            'sku' => $row->sku ?? '-',
            'name' => $row->name ?? '-',
            'category' => $row->category ?? '-',
            'address' => $row->address ?? '-',
            'is_bundle' => (bool) $row->is_bundle,
            'item_type' => ((bool) $row->is_bundle) ? 'Bundle' : 'Reguler',
            'stock_as_of' => $regular,
            'damaged_stock_as_of' => $damaged,
            'total_stock_as_of' => $regular + $damaged,
            'safety_stock' => $safety,
            'gap' => max(0, $safety - $regular),
            'inbound_as_of' => (int) $row->inbound_as_of,
            'outbound_as_of' => (int) $row->outbound_as_of,
            'last_mutation_at' => $row->last_mutation_at ? Carbon::parse($row->last_mutation_at)->format('Y-m-d H:i') : '-',
            'status' => $this->statusLabel($regular, $safety),
        ];
    }

    private function statusLabel(int $stock, int $safety): string
    {
        if ($stock < 0) {
            return 'Negative';
        }
        if ($stock === 0) {
            return 'Zero';
        }
        if ($safety > 0 && $stock < $safety) {
            return 'Low Stock';
        }

        return 'Available';
    }

    private function asOfDate(Request $request): Carbon
    {
        try {
            return Carbon::parse($request->input('as_of_date') ?: now()->toDateString())->endOfDay();
        } catch (\Throwable) {
            return now()->endOfDay();
        }
    }

    private function exportFilters(Request $request, Carbon $asOf): array
    {
        return [
            'as_of_date' => $asOf->toDateString(),
            'q' => trim((string) $request->input('q', '')),
            'category_id' => $request->input('category_id', ''),
            'status' => $request->input('status', ''),
            'stock_type' => $request->input('stock_type', 'regular'),
            'include_zero' => $request->boolean('include_zero', true),
        ];
    }
}
