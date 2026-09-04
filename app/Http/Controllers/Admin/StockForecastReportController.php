<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockForecastReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.stock-forecast.index', [
            'dataUrl' => route('admin.reports.stock-forecast.data'),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'defaultHistoryDays' => 30,
            'defaultCoverageDays' => 30,
        ]);
    }

    public function data(Request $request)
    {
        $historyDays = $this->boundedDays($request->input('history_days'), 30);
        $coverageDays = $this->boundedDays($request->input('coverage_days'), 30);
        $includeSafetyStock = $request->boolean('include_safety_stock');
        $dateTo = now()->endOfDay();
        $dateFrom = now()->subDays($historyDays - 1)->startOfDay();

        $projection = $this->forecastProjection(
            $dateFrom,
            $dateTo,
            $historyDays,
            $coverageDays,
            $includeSafetyStock
        );
        $base = DB::query()->fromSub($projection, 'forecast');
        $this->applyFilters($base, $request);

        $recordsTotal = DB::table('items')
            ->when(Schema::hasColumn('items', 'is_bundle'), fn ($query) => $query->where('is_bundle', false))
            ->when(Schema::hasColumn('items', 'is_active'), fn ($query) => $query->where('is_active', true))
            ->count();
        $recordsFiltered = (clone $base)->count();

        $summaryRow = (clone $base)
            ->selectRaw('COUNT(*) as total_sku')
            ->selectRaw('COALESCE(SUM(sales_qty), 0) as sales_qty')
            ->selectRaw('COALESCE(SUM(current_stock), 0) as current_stock')
            ->selectRaw('COALESCE(SUM(target_stock), 0) as target_stock')
            ->selectRaw('COALESCE(SUM(suggested_purchase), 0) as suggested_purchase')
            ->selectRaw('SUM(CASE WHEN suggested_purchase > 0 THEN 1 ELSE 0 END) as purchase_sku')
            ->selectRaw("SUM(CASE WHEN forecast_status = 'critical' THEN 1 ELSE 0 END) as critical_sku")
            ->selectRaw("SUM(CASE WHEN forecast_status = 'reorder' THEN 1 ELSE 0 END) as reorder_sku")
            ->selectRaw("SUM(CASE WHEN forecast_status = 'sufficient' THEN 1 ELSE 0 END) as sufficient_sku")
            ->selectRaw("SUM(CASE WHEN forecast_status = 'no_sales' THEN 1 ELSE 0 END) as no_sales_sku")
            ->first();

        $salesQty = (int) ($summaryRow->sales_qty ?? 0);
        $currentStock = (int) ($summaryRow->current_stock ?? 0);
        $summary = [
            'total_sku' => (int) ($summaryRow->total_sku ?? 0),
            'sales_qty' => $salesQty,
            'average_daily_sales' => round($salesQty / $historyDays, 2),
            'current_stock' => $currentStock,
            'target_stock' => (int) ($summaryRow->target_stock ?? 0),
            'suggested_purchase' => (int) ($summaryRow->suggested_purchase ?? 0),
            'purchase_sku' => (int) ($summaryRow->purchase_sku ?? 0),
            'critical_sku' => (int) ($summaryRow->critical_sku ?? 0),
            'reorder_sku' => (int) ($summaryRow->reorder_sku ?? 0),
            'sufficient_sku' => (int) ($summaryRow->sufficient_sku ?? 0),
            'no_sales_sku' => (int) ($summaryRow->no_sales_sku ?? 0),
            'overall_days_cover' => $salesQty > 0 ? round(($currentStock * $historyDays) / $salesQty, 1) : null,
        ];

        $analytics = [
            'daily' => $this->dailyDemand($base, $dateFrom, $dateTo),
            'top_procurement' => $this->topProcurement($base),
            'categories' => $this->categoryProcurement($base),
            'status' => [
                ['label' => 'Stok habis', 'value' => $summary['critical_sku']],
                ['label' => 'Perlu pengadaan', 'value' => $summary['reorder_sku']],
                ['label' => 'Stok mencukupi', 'value' => $summary['sufficient_sku']],
                ['label' => 'Tanpa penjualan', 'value' => $summary['no_sales_sku']],
            ],
        ];

        [$start, $length] = $this->pagination($request);
        $rows = (clone $base)
            ->orderByRaw("CASE forecast_status WHEN 'critical' THEN 0 WHEN 'reorder' THEN 1 WHEN 'sufficient' THEN 2 ELSE 3 END")
            ->orderByDesc('suggested_purchase')
            ->orderByDesc('sales_qty')
            ->orderBy('sku')
            ->skip($start)
            ->take($length)
            ->get()
            ->map(fn ($row) => [
                'item_id' => (int) $row->item_id,
                'sku' => $row->sku ?: '-',
                'name' => $row->name ?: '-',
                'category' => $row->category ?: '-',
                'address' => $row->address ?: '-',
                'direct_sales_qty' => (int) $row->direct_sales_qty,
                'bundle_demand_qty' => (int) $row->bundle_demand_qty,
                'sales_qty' => (int) $row->sales_qty,
                'average_daily_sales' => (float) $row->average_daily_sales,
                'current_stock' => (int) $row->current_stock,
                'safety_stock' => (int) $row->safety_stock,
                'target_stock' => (int) $row->target_stock,
                'suggested_purchase' => (int) $row->suggested_purchase,
                'days_cover' => $row->days_cover === null ? null : (float) $row->days_cover,
                'forecast_status' => $row->forecast_status,
                'last_sale_at' => $row->last_sale_at
                    ? Carbon::parse($row->last_sale_at)->format('Y-m-d H:i')
                    : null,
            ])->values();

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'parameters' => [
                'history_days' => $historyDays,
                'coverage_days' => $coverageDays,
                'include_safety_stock' => $includeSafetyStock,
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
            ],
            'summary' => $summary,
            'analytics' => $analytics,
            'data' => $rows,
        ]);
    }

    private function forecastProjection(
        Carbon $dateFrom,
        Carbon $dateTo,
        int $historyDays,
        int $coverageDays,
        bool $includeSafetyStock
    ): Builder {
        $demand = $this->demandAggregate($dateFrom, $dateTo);
        $salesExpr = 'COALESCE(d.total_qty, 0)';
        $stockExpr = 'COALESCE(item_stock.stock, 0)';
        $hasSafetyStock = Schema::hasColumn('items', 'safety_stock');
        $safetyExpr = $includeSafetyStock && $hasSafetyStock ? 'COALESCE(i.safety_stock, 0)' : '0';
        $targetDemandExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST((({$salesExpr} * {$coverageDays}) + {$historyDays} - 1) / {$historyDays} AS INTEGER)"
            : "CEIL(({$salesExpr} * {$coverageDays}) / {$historyDays})";
        $targetExpr = "({$targetDemandExpr} + {$safetyExpr})";
        $suggestionExpr = "CASE WHEN {$targetExpr} > {$stockExpr} THEN {$targetExpr} - {$stockExpr} ELSE 0 END";
        $statusExpr = "CASE
            WHEN {$salesExpr} <= 0 THEN 'no_sales'
            WHEN {$stockExpr} <= 0 THEN 'critical'
            WHEN {$suggestionExpr} > 0 THEN 'reorder'
            ELSE 'sufficient'
        END";

        $query = DB::table('items as i')
            ->leftJoin('item_stocks as item_stock', 'item_stock.item_id', '=', 'i.id')
            ->leftJoin('categories as category', 'category.id', '=', 'i.category_id')
            ->leftJoinSub($demand, 'd', 'd.item_id', '=', 'i.id')
            ->select([
                'i.id as item_id',
                'i.category_id',
                'i.sku',
                'i.name',
                DB::raw("CASE WHEN i.category_id = 0 THEN 'Tanpa Kategori' ELSE COALESCE(category.name, '-') END as category"),
                DB::raw(Schema::hasColumn('items', 'address') ? "COALESCE(i.address, '-') as address" : "'-' as address"),
                DB::raw('COALESCE(d.direct_qty, 0) as direct_sales_qty'),
                DB::raw('COALESCE(d.bundle_qty, 0) as bundle_demand_qty'),
                DB::raw("{$salesExpr} as sales_qty"),
                DB::raw("ROUND(({$salesExpr} * 1.0) / {$historyDays}, 2) as average_daily_sales"),
                DB::raw("{$stockExpr} as current_stock"),
                DB::raw($hasSafetyStock ? 'COALESCE(i.safety_stock, 0) as safety_stock' : '0 as safety_stock'),
                DB::raw("{$targetExpr} as target_stock"),
                DB::raw("{$suggestionExpr} as suggested_purchase"),
                DB::raw("CASE WHEN {$salesExpr} > 0 THEN ROUND(({$stockExpr} * {$historyDays} * 1.0) / {$salesExpr}, 1) ELSE NULL END as days_cover"),
                DB::raw("{$statusExpr} as forecast_status"),
                DB::raw('d.last_sale_at'),
            ]);

        if (Schema::hasColumn('items', 'is_bundle')) {
            $query->where('i.is_bundle', false);
        }
        if (Schema::hasColumn('items', 'is_active')) {
            $query->where('i.is_active', true);
        }

        return $query;
    }

    private function demandAggregate(Carbon $dateFrom, Carbon $dateTo): Builder
    {
        $rawDemand = $this->rawDemand($dateFrom, $dateTo);

        return DB::query()
            ->fromSub($rawDemand, 'raw_demand')
            ->select('item_id')
            ->selectRaw('COALESCE(SUM(direct_qty), 0) as direct_qty')
            ->selectRaw('COALESCE(SUM(bundle_qty), 0) as bundle_qty')
            ->selectRaw('COALESCE(SUM(direct_qty + bundle_qty), 0) as total_qty')
            ->selectRaw('MAX(last_sale_at) as last_sale_at')
            ->groupBy('item_id');
    }

    private function rawDemand(Carbon $dateFrom, Carbon $dateTo, bool $withDate = false): Builder
    {
        $direct = DB::table('packer_scan_outs as pso')
            ->join('resis as r', 'r.id', '=', 'pso.resi_id')
            ->join('resi_details as rd', 'rd.resi_id', '=', 'r.id')
            ->join('items as sold_item', DB::raw('LOWER(sold_item.sku)'), '=', DB::raw('LOWER(rd.sku)'))
            ->leftJoin('packer_scan_exceptions as exception_sku', DB::raw('LOWER(exception_sku.sku)'), '=', DB::raw('LOWER(rd.sku)'))
            ->whereDate('pso.scan_date', '>=', $dateFrom->toDateString())
            ->whereDate('pso.scan_date', '<=', $dateTo->toDateString())
            ->whereNull('exception_sku.id')
            ->where(fn ($query) => $query->whereNull('r.status')->orWhere('r.status', '!=', 'canceled'))
            ->when(Schema::hasColumn('items', 'is_bundle'), fn ($query) => $query->where('sold_item.is_bundle', false));

        if ($withDate) {
            $direct->selectRaw('pso.scan_date as demand_date, sold_item.id as item_id, SUM(rd.qty) as direct_qty, 0 as bundle_qty, MAX(pso.scanned_at) as last_sale_at')
                ->groupBy('pso.scan_date', 'sold_item.id');
        } else {
            $direct->selectRaw('sold_item.id as item_id, SUM(rd.qty) as direct_qty, 0 as bundle_qty, MAX(pso.scanned_at) as last_sale_at')
                ->groupBy('sold_item.id');
        }

        $bundle = DB::table('packer_scan_outs as pso')
            ->join('resis as r', 'r.id', '=', 'pso.resi_id')
            ->join('resi_details as rd', 'rd.resi_id', '=', 'r.id')
            ->join('items as bundle_item', DB::raw('LOWER(bundle_item.sku)'), '=', DB::raw('LOWER(rd.sku)'))
            ->join('item_bundles as bundle_component', 'bundle_component.bundle_item_id', '=', 'bundle_item.id')
            ->join('items as component_item', 'component_item.id', '=', 'bundle_component.component_item_id')
            ->leftJoin('packer_scan_exceptions as exception_sku', DB::raw('LOWER(exception_sku.sku)'), '=', DB::raw('LOWER(rd.sku)'))
            ->whereDate('pso.scan_date', '>=', $dateFrom->toDateString())
            ->whereDate('pso.scan_date', '<=', $dateTo->toDateString())
            ->whereNull('exception_sku.id')
            ->where(fn ($query) => $query->whereNull('r.status')->orWhere('r.status', '!=', 'canceled'))
            ->when(Schema::hasColumn('items', 'is_bundle'), fn ($query) => $query->where('bundle_item.is_bundle', true));

        if ($withDate) {
            $bundle->selectRaw('pso.scan_date as demand_date, component_item.id as item_id, 0 as direct_qty, SUM(rd.qty * bundle_component.qty) as bundle_qty, MAX(pso.scanned_at) as last_sale_at')
                ->groupBy('pso.scan_date', 'component_item.id');
        } else {
            $bundle->selectRaw('component_item.id as item_id, 0 as direct_qty, SUM(rd.qty * bundle_component.qty) as bundle_qty, MAX(pso.scanned_at) as last_sale_at')
                ->groupBy('component_item.id');
        }

        return $direct->unionAll($bundle);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('category_id')) {
            $categoryId = (int) $request->input('category_id');
            $query->where('forecast.category_id', $categoryId);
        }

        $status = (string) $request->input('status', '');
        if (in_array($status, ['critical', 'reorder', 'sufficient', 'no_sales'], true)) {
            $query->where('forecast.forecast_status', $status);
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($nested) use ($search) {
                $nested->where('forecast.sku', 'like', "%{$search}%")
                    ->orWhere('forecast.name', 'like', "%{$search}%")
                    ->orWhere('forecast.category', 'like', "%{$search}%")
                    ->orWhere('forecast.address', 'like', "%{$search}%");
            });
        }
    }

    private function dailyDemand(Builder $filteredForecast, Carbon $dateFrom, Carbon $dateTo): array
    {
        $filteredItems = (clone $filteredForecast)->select('forecast.item_id');
        $rawDaily = $this->rawDemand($dateFrom, $dateTo, true);

        $rows = DB::query()
            ->fromSub($rawDaily, 'daily_demand')
            ->joinSub($filteredItems, 'filtered_items', 'filtered_items.item_id', '=', 'daily_demand.item_id')
            ->select('daily_demand.demand_date')
            ->selectRaw('COALESCE(SUM(daily_demand.direct_qty), 0) as direct_qty')
            ->selectRaw('COALESCE(SUM(daily_demand.bundle_qty), 0) as bundle_qty')
            ->selectRaw('COALESCE(SUM(daily_demand.direct_qty + daily_demand.bundle_qty), 0) as total_qty')
            ->groupBy('daily_demand.demand_date')
            ->orderBy('daily_demand.demand_date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->demand_date)->toDateString());

        $result = [];
        for ($date = $dateFrom->copy()->startOfDay(); $date->lte($dateTo); $date->addDay()) {
            $key = $date->toDateString();
            $row = $rows->get($key);
            $result[] = [
                'date' => $key,
                'direct_qty' => (int) ($row->direct_qty ?? 0),
                'bundle_qty' => (int) ($row->bundle_qty ?? 0),
                'total_qty' => (int) ($row->total_qty ?? 0),
            ];
        }

        return $result;
    }

    private function topProcurement(Builder $base): array
    {
        return (clone $base)
            ->where('forecast.suggested_purchase', '>', 0)
            ->select(['forecast.sku as label', 'forecast.name as description', 'forecast.suggested_purchase as value'])
            ->selectRaw('forecast.days_cover as secondary')
            ->orderByDesc('forecast.suggested_purchase')
            ->limit(7)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'description' => $row->description,
                'value' => (int) $row->value,
                'secondary' => $row->secondary === null ? null : (float) $row->secondary,
            ])->all();
    }

    private function categoryProcurement(Builder $base): array
    {
        return (clone $base)
            ->where('forecast.suggested_purchase', '>', 0)
            ->select('forecast.category as label')
            ->selectRaw('COALESCE(SUM(forecast.suggested_purchase), 0) as value')
            ->selectRaw('COUNT(*) as sku_count')
            ->groupBy('forecast.category')
            ->orderByDesc('value')
            ->limit(7)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'value' => (int) $row->value,
                'sku_count' => (int) $row->sku_count,
            ])->all();
    }

    private function boundedDays(mixed $value, int $default): int
    {
        $days = filter_var($value, FILTER_VALIDATE_INT);
        if ($days === false || $days < 1) {
            return $default;
        }

        return min($days, 365);
    }

    private function pagination(Request $request): array
    {
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        if (! in_array($length, [10, 25, 50, 100], true)) {
            $length = 10;
        }

        return [$start, $length];
    }
}
