<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

        // The sales window is read once; cards, charts and table rows reuse the result.
        [$demandByItem, $dailyByItem] = $this->buildDemandMaps($dateFrom, $dateTo);
        [$itemQuery, $recordsTotal] = $this->stockableItemsQuery($request);

        $forecastRows = $itemQuery->get()->map(function ($item) use (
            $demandByItem,
            $historyDays,
            $coverageDays,
            $includeSafetyStock
        ) {
            $demand = $demandByItem[$item->item_id] ?? [
                'direct_qty' => 0,
                'bundle_qty' => 0,
                'total_qty' => 0,
                'last_sale_at' => null,
            ];
            $salesQty = (int) $demand['total_qty'];
            $currentStock = (int) $item->current_stock;
            $safetyStock = (int) $item->safety_stock;
            $targetDemand = $salesQty > 0
                ? intdiv(($salesQty * $coverageDays) + $historyDays - 1, $historyDays)
                : 0;
            $targetStock = $targetDemand + ($includeSafetyStock ? $safetyStock : 0);
            $suggestedPurchase = max(0, $targetStock - $currentStock);

            $status = match (true) {
                $salesQty <= 0 => 'no_sales',
                $currentStock <= 0 => 'critical',
                $suggestedPurchase > 0 => 'reorder',
                default => 'sufficient',
            };

            return [
                'item_id' => (int) $item->item_id,
                'sku' => $item->sku ?: '-',
                'name' => $item->name ?: '-',
                'category' => $item->category ?: '-',
                'address' => $item->address ?: '-',
                'direct_sales_qty' => (int) $demand['direct_qty'],
                'bundle_demand_qty' => (int) $demand['bundle_qty'],
                'sales_qty' => $salesQty,
                'average_daily_sales' => round($salesQty / $historyDays, 2),
                'current_stock' => $currentStock,
                'safety_stock' => $safetyStock,
                'target_stock' => $targetStock,
                'suggested_purchase' => $suggestedPurchase,
                'days_cover' => $salesQty > 0
                    ? round(($currentStock * $historyDays) / $salesQty, 1)
                    : null,
                'forecast_status' => $status,
                'last_sale_at' => $demand['last_sale_at'],
            ];
        });

        $statusFilter = (string) $request->input('status', '');
        if (in_array($statusFilter, ['critical', 'reorder', 'sufficient', 'no_sales'], true)) {
            $forecastRows = $forecastRows->where('forecast_status', $statusFilter)->values();
        }

        $recordsFiltered = $forecastRows->count();
        $summary = $this->summary($forecastRows, $historyDays);
        $analytics = [
            'daily' => $this->dailyAnalytics($forecastRows, $dailyByItem, $dateFrom, $dateTo),
            'top_procurement' => $this->topProcurement($forecastRows),
            'categories' => $this->categoryProcurement($forecastRows),
            'status' => [
                ['label' => 'Stok habis', 'value' => $summary['critical_sku']],
                ['label' => 'Perlu pengadaan', 'value' => $summary['reorder_sku']],
                ['label' => 'Stok mencukupi', 'value' => $summary['sufficient_sku']],
                ['label' => 'Tanpa penjualan', 'value' => $summary['no_sales_sku']],
            ],
        ];

        $statusRank = ['critical' => 0, 'reorder' => 1, 'sufficient' => 2, 'no_sales' => 3];
        $sortedRows = $forecastRows->sort(function (array $left, array $right) use ($statusRank) {
            $statusOrder = ($statusRank[$left['forecast_status']] ?? 9) <=> ($statusRank[$right['forecast_status']] ?? 9);
            if ($statusOrder !== 0) {
                return $statusOrder;
            }
            if ($left['suggested_purchase'] !== $right['suggested_purchase']) {
                return $right['suggested_purchase'] <=> $left['suggested_purchase'];
            }
            if ($left['sales_qty'] !== $right['sales_qty']) {
                return $right['sales_qty'] <=> $left['sales_qty'];
            }

            return strcasecmp($left['sku'], $right['sku']);
        })->values();

        [$start, $length] = $this->pagination($request);

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
            'data' => $sortedRows->slice($start, $length)->values(),
        ]);
    }

    /**
     * @return array{0: array<int,array<string,mixed>>, 1: array<int,array<string,array<string,int>>>}
     */
    private function buildDemandMaps(Carbon $dateFrom, Carbon $dateTo): array
    {
        $salesRows = $this->salesByDayAndItem($dateFrom, $dateTo)->get();
        $bundleIds = $salesRows
            ->where('is_bundle', 1)
            ->pluck('sold_item_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $components = collect();
        if ($bundleIds->isNotEmpty() && Schema::hasTable('item_bundles')) {
            $components = DB::table('item_bundles')
                ->whereIn('bundle_item_id', $bundleIds)
                ->get(['bundle_item_id', 'component_item_id', 'qty'])
                ->groupBy('bundle_item_id');
        }

        $demandByItem = [];
        $dailyByItem = [];
        foreach ($salesRows as $sale) {
            $date = Carbon::parse($sale->sale_date)->toDateString();
            $saleQty = max(0, (int) $sale->qty);
            if ($saleQty <= 0) {
                continue;
            }

            if ((bool) $sale->is_bundle) {
                foreach ($components->get($sale->sold_item_id, collect()) as $component) {
                    $this->addDemand(
                        $demandByItem,
                        $dailyByItem,
                        (int) $component->component_item_id,
                        $date,
                        0,
                        $saleQty * max(1, (int) $component->qty),
                        $sale->last_sale_at
                    );
                }
            } else {
                $this->addDemand(
                    $demandByItem,
                    $dailyByItem,
                    (int) $sale->sold_item_id,
                    $date,
                    $saleQty,
                    0,
                    $sale->last_sale_at
                );
            }
        }

        return [$demandByItem, $dailyByItem];
    }

    private function salesByDayAndItem(Carbon $dateFrom, Carbon $dateTo): Builder
    {
        $query = DB::table('packer_scan_outs as pso')
            ->join('resis as r', 'r.id', '=', 'pso.resi_id')
            ->join('resi_details as rd', 'rd.resi_id', '=', 'r.id');

        if (DB::connection()->getDriverName() === 'sqlite') {
            $query->join('items as sold_item', DB::raw('LOWER(sold_item.sku)'), '=', DB::raw('LOWER(rd.sku)'));
        } else {
            $query->join('items as sold_item', 'sold_item.sku', '=', 'rd.sku');
        }

        if (Schema::hasTable('packer_scan_exceptions')) {
            if (DB::connection()->getDriverName() === 'sqlite') {
                $query->leftJoin('packer_scan_exceptions as exception_sku', DB::raw('LOWER(exception_sku.sku)'), '=', DB::raw('LOWER(rd.sku)'));
            } else {
                $query->leftJoin('packer_scan_exceptions as exception_sku', 'exception_sku.sku', '=', 'rd.sku');
            }
            $query->whereNull('exception_sku.id');
        }

        if (Schema::hasColumn('resis', 'status')) {
            $query->where(fn ($nested) => $nested->whereNull('r.status')->orWhere('r.status', '!=', 'canceled'));
        }

        return $query
            ->whereDate('pso.scan_date', '>=', $dateFrom->toDateString())
            ->whereDate('pso.scan_date', '<=', $dateTo->toDateString())
            ->selectRaw('pso.scan_date as sale_date, sold_item.id as sold_item_id')
            ->selectRaw(Schema::hasColumn('items', 'is_bundle') ? 'sold_item.is_bundle' : '0 as is_bundle')
            ->selectRaw('COALESCE(SUM(rd.qty), 0) as qty')
            ->selectRaw('MAX(pso.scanned_at) as last_sale_at')
            ->groupBy('pso.scan_date', 'sold_item.id')
            ->when(Schema::hasColumn('items', 'is_bundle'), fn ($builder) => $builder->groupBy('sold_item.is_bundle'));
    }

    private function addDemand(
        array &$demandByItem,
        array &$dailyByItem,
        int $itemId,
        string $date,
        int $directQty,
        int $bundleQty,
        ?string $lastSaleAt
    ): void {
        $demandByItem[$itemId] ??= [
            'direct_qty' => 0,
            'bundle_qty' => 0,
            'total_qty' => 0,
            'last_sale_at' => null,
        ];
        $demandByItem[$itemId]['direct_qty'] += $directQty;
        $demandByItem[$itemId]['bundle_qty'] += $bundleQty;
        $demandByItem[$itemId]['total_qty'] += $directQty + $bundleQty;
        if ($lastSaleAt && (!$demandByItem[$itemId]['last_sale_at'] || $lastSaleAt > $demandByItem[$itemId]['last_sale_at'])) {
            $demandByItem[$itemId]['last_sale_at'] = $lastSaleAt;
        }

        $dailyByItem[$itemId][$date] ??= ['direct_qty' => 0, 'bundle_qty' => 0];
        $dailyByItem[$itemId][$date]['direct_qty'] += $directQty;
        $dailyByItem[$itemId][$date]['bundle_qty'] += $bundleQty;
    }

    /**
     * @return array{0: Builder, 1: int}
     */
    private function stockableItemsQuery(Request $request): array
    {
        // Existing installations may contain duplicate stock rows. The oldest row mirrors
        // ItemStock::where(...)->first(), which is also used by stock mutations.
        $stockKeys = DB::table('item_stocks')
            ->select('item_id')
            ->selectRaw('MIN(id) as stock_row_id')
            ->groupBy('item_id');

        $query = DB::table('items as i')
            ->leftJoinSub($stockKeys, 'stock_key', 'stock_key.item_id', '=', 'i.id')
            ->leftJoin('item_stocks as item_stock', 'item_stock.id', '=', 'stock_key.stock_row_id')
            ->leftJoin('categories as category', 'category.id', '=', 'i.category_id')
            ->select([
                'i.id as item_id',
                'i.category_id',
                'i.sku',
                'i.name',
                DB::raw("CASE WHEN i.category_id = 0 THEN 'Tanpa Kategori' ELSE COALESCE(category.name, '-') END as category"),
                DB::raw(Schema::hasColumn('items', 'address') ? "COALESCE(i.address, '-') as address" : "'-' as address"),
                DB::raw('COALESCE(item_stock.stock, 0) as current_stock'),
                DB::raw(Schema::hasColumn('items', 'safety_stock') ? 'COALESCE(i.safety_stock, 0) as safety_stock' : '0 as safety_stock'),
            ]);

        if (Schema::hasColumn('items', 'is_bundle')) {
            $query->where('i.is_bundle', false);
        }
        if (Schema::hasColumn('items', 'is_active')) {
            $query->where('i.is_active', true);
        }

        $recordsTotal = (clone $query)->count();

        if ($request->filled('category_id')) {
            $query->where('i.category_id', (int) $request->input('category_id'));
        }
        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($nested) use ($search) {
                $nested->where('i.sku', 'like', "%{$search}%")
                    ->orWhere('i.name', 'like', "%{$search}%")
                    ->orWhere('category.name', 'like', "%{$search}%");
                if (Schema::hasColumn('items', 'address')) {
                    $nested->orWhere('i.address', 'like', "%{$search}%");
                }
            });
        }

        return [$query, $recordsTotal];
    }

    private function summary(Collection $rows, int $historyDays): array
    {
        $salesQty = (int) $rows->sum('sales_qty');
        $currentStock = (int) $rows->sum('current_stock');

        return [
            'total_sku' => $rows->count(),
            'sales_qty' => $salesQty,
            'average_daily_sales' => round($salesQty / $historyDays, 2),
            'current_stock' => $currentStock,
            'target_stock' => (int) $rows->sum('target_stock'),
            'suggested_purchase' => (int) $rows->sum('suggested_purchase'),
            'purchase_sku' => $rows->where('suggested_purchase', '>', 0)->count(),
            'critical_sku' => $rows->where('forecast_status', 'critical')->count(),
            'reorder_sku' => $rows->where('forecast_status', 'reorder')->count(),
            'sufficient_sku' => $rows->where('forecast_status', 'sufficient')->count(),
            'no_sales_sku' => $rows->where('forecast_status', 'no_sales')->count(),
            'overall_days_cover' => $salesQty > 0
                ? round(($currentStock * $historyDays) / $salesQty, 1)
                : null,
        ];
    }

    private function dailyAnalytics(Collection $rows, array $dailyByItem, Carbon $dateFrom, Carbon $dateTo): array
    {
        $selectedIds = array_flip($rows->pluck('item_id')->all());
        $totals = [];
        foreach ($dailyByItem as $itemId => $days) {
            if (! isset($selectedIds[$itemId])) {
                continue;
            }
            foreach ($days as $date => $quantities) {
                $totals[$date] ??= ['direct_qty' => 0, 'bundle_qty' => 0];
                $totals[$date]['direct_qty'] += $quantities['direct_qty'];
                $totals[$date]['bundle_qty'] += $quantities['bundle_qty'];
            }
        }

        $result = [];
        for ($date = $dateFrom->copy()->startOfDay(); $date->lte($dateTo); $date->addDay()) {
            $key = $date->toDateString();
            $directQty = (int) ($totals[$key]['direct_qty'] ?? 0);
            $bundleQty = (int) ($totals[$key]['bundle_qty'] ?? 0);
            $result[] = [
                'date' => $key,
                'direct_qty' => $directQty,
                'bundle_qty' => $bundleQty,
                'total_qty' => $directQty + $bundleQty,
            ];
        }

        return $result;
    }

    private function topProcurement(Collection $rows): array
    {
        return $rows
            ->where('suggested_purchase', '>', 0)
            ->sortByDesc('suggested_purchase')
            ->take(7)
            ->map(fn (array $row) => [
                'label' => $row['sku'],
                'description' => $row['name'],
                'value' => $row['suggested_purchase'],
                'secondary' => $row['days_cover'],
            ])->values()->all();
    }

    private function categoryProcurement(Collection $rows): array
    {
        return $rows
            ->where('suggested_purchase', '>', 0)
            ->groupBy('category')
            ->map(fn (Collection $categoryRows, string $category) => [
                'label' => $category,
                'value' => (int) $categoryRows->sum('suggested_purchase'),
                'sku_count' => $categoryRows->count(),
            ])
            ->sortByDesc('value')
            ->take(7)
            ->values()
            ->all();
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
