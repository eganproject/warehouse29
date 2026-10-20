<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockPeriodReport
{
    public const MOVEMENTS = [
        'fast' => 'Fast Moving',
        'medium' => 'Medium Moving',
        'slow' => 'Slow Moving',
        'non' => 'Non Moving',
    ];

    public function days(array $filters): int
    {
        return (int) Carbon::parse($filters['date_from'])->diffInDays(Carbon::parse($filters['date_to'])) + 1;
    }

    public function query(array $filters): Builder
    {
        $columns = array_flip(Schema::getColumnListing('items'));
        $start = Carbon::parse($filters['date_from'])->startOfDay();
        $end = Carbon::parse($filters['date_to'])->addDay()->startOfDay();
        $table = $filters['stock_type'] === 'damaged' ? 'damaged_stock_mutations' : 'stock_mutations';
        // Filter toko (tab pergerakan): qty masuk/keluar, frekuensi & tanggal keluar hanya dari mutasi
        // resi toko tsb. Stok awal/akhir tetap stok gudang karena stok tidak dipisah per toko.
        $toko = self::tokoCondition($filters);
        $mutations = DB::table($table.' as sm')->select('sm.item_id')
            ->where('sm.occurred_at', '<', $end)
            ->selectRaw("SUM(CASE WHEN sm.occurred_at < ? THEN CASE WHEN sm.direction = 'in' THEN sm.qty ELSE -sm.qty END ELSE 0 END) as opening", [$start])
            ->selectRaw("SUM(CASE WHEN sm.occurred_at >= ? AND sm.direction = 'in'{$toko} THEN sm.qty ELSE 0 END) as qty_in", [$start])
            ->selectRaw("SUM(CASE WHEN sm.occurred_at >= ? AND sm.direction = 'out'{$toko} THEN sm.qty ELSE 0 END) as qty_out", [$start])
            ->selectRaw("SUM(CASE WHEN sm.direction = 'in' THEN sm.qty ELSE -sm.qty END) as closing")
            ->selectRaw("COUNT(DISTINCT CASE WHEN sm.occurred_at >= ? AND sm.direction = 'out' AND sm.qty > 0{$toko} THEN DATE(sm.occurred_at) END) as outgoing_days", [$start])
            ->selectRaw("COUNT(CASE WHEN sm.occurred_at >= ? AND sm.direction = 'out' AND sm.qty > 0{$toko} THEN 1 END) as outgoing_frequency", [$start])
            ->selectRaw("MAX(CASE WHEN sm.direction = 'out' AND sm.qty > 0{$toko} THEN sm.occurred_at END) as last_out_at")
            ->groupBy('sm.item_id');
        if ($toko !== '') {
            StockMutationToko::join($mutations, 'sm');
        }

        $items = DB::table('items as i')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->leftJoinSub($mutations, 'm', 'm.item_id', '=', 'i.id')
            ->when(isset($columns['is_active']), fn ($q) => $q->where('i.is_active', true))
            ->when(isset($columns['is_bundle']), fn ($q) => $q->where(fn ($nested) => $nested->where('i.is_bundle', false)->orWhereNull('i.is_bundle')))
            ->select('i.id', 'i.sku', 'i.name', 'i.category_id')
            ->selectRaw(isset($columns['address']) ? 'i.address' : "'' as address")
            ->selectRaw(isset($columns['uom']) ? 'i.uom' : "'' as uom")
            ->selectRaw(isset($columns['safety_stock']) ? 'i.safety_stock' : '0 as safety_stock')
            ->selectRaw("COALESCE(c.name, 'Tanpa Kategori') as category")
            ->selectRaw('COALESCE(m.opening, 0) as opening, COALESCE(m.qty_in, 0) as qty_in, COALESCE(m.qty_out, 0) as qty_out, COALESCE(m.closing, 0) as closing')
            ->selectRaw('COALESCE(m.outgoing_days, 0) as outgoing_days, COALESCE(m.outgoing_frequency, 0) as outgoing_frequency, m.last_out_at')
            ->selectRaw('COALESCE(m.qty_out, 0) * 1.0 / ? as average_out', [$this->days($filters)]);

        $ranked = DB::query()->fromSub($items, 'stock_base')
            ->select('stock_base.*')
            ->selectRaw('SUM(qty_out) OVER () as total_qty_out')
            ->selectRaw('SUM(qty_out) OVER (ORDER BY qty_out DESC, sku ASC, id ASC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) as cumulative_qty_out');

        $classified = DB::query()->fromSub($ranked, 'stock_ranked')
            ->select('stock_ranked.*')
            ->selectRaw('CASE WHEN total_qty_out > 0 THEN qty_out * 100.0 / total_qty_out ELSE 0 END as contribution_percent')
            ->selectRaw('CASE WHEN total_qty_out > 0 THEN cumulative_qty_out * 100.0 / total_qty_out ELSE 0 END as cumulative_percent')
            ->selectRaw("CASE
                WHEN qty_out <= 0 OR total_qty_out <= 0 THEN 'non'
                WHEN (cumulative_qty_out - qty_out) * 100.0 / total_qty_out < 70 THEN 'fast'
                WHEN (cumulative_qty_out - qty_out) * 100.0 / total_qty_out < 90 THEN 'medium'
                ELSE 'slow'
            END as movement")
            ->selectRaw('CASE WHEN average_out > 0 THEN closing * 1.0 / average_out ELSE NULL END as days_cover');

        $query = DB::query()->fromSub($classified, 'stock_report');
        if (($filters['category_id'] ?? '') !== '') {
            $category = (int) $filters['category_id'];
            $query->where(function ($q) use ($category) {
                $q->where('category_id', $category);
                if ($category === 0) {
                    $q->orWhereNull('category_id');
                }
            });
        }
        $search = trim($filters['q'] ?? '');
        if ($search !== '') {
            $query->where('sku', $search);
        }

        match ($filters['status'] ?? '') {
            'positive' => $query->where('closing', '>', 0),
            'zero' => $query->where('closing', 0),
            'negative' => $query->where('closing', '<', 0),
            'low' => $query->where('safety_stock', '>', 0)->whereColumn('closing', '<', 'safety_stock'),
            default => null,
        };
        if (($filters['tab'] ?? 'balance') === 'movement' && ($filters['movement'] ?? '') !== '') {
            $query->where('movement', $filters['movement']);
        }

        return $query;
    }

    /**
     * Toko hanya berlaku di tab pergerakan (tab saldo harus tetap memenuhi awal + in − out = akhir).
     */
    public static function tokoId(array $filters): int|string|null
    {
        return ($filters['tab'] ?? 'balance') === 'movement'
            ? TokoFilter::normalize($filters['toko_id'] ?? null)
            : null;
    }

    private static function tokoCondition(array $filters): string
    {
        $tokoId = self::tokoId($filters);

        return $tokoId === null ? '' : ' AND '.StockMutationToko::condition($tokoId);
    }

    /**
     * Barang keluar per toko untuk SKU yang lolos filter (sheet export pergerakan).
     */
    public function outgoingPerToko(array $filters): Collection
    {
        $start = Carbon::parse($filters['date_from'])->startOfDay();
        $end = Carbon::parse($filters['date_to'])->addDay()->startOfDay();
        $table = $filters['stock_type'] === 'damaged' ? 'damaged_stock_mutations' : 'stock_mutations';
        $filteredItems = $this->query($filters)->select('id');

        $query = DB::table($table.' as sm')
            ->joinSub($filteredItems, 'filtered_items', 'filtered_items.id', '=', 'sm.item_id')
            ->where('sm.direction', 'out')
            ->where('sm.qty', '>', 0)
            ->where('sm.occurred_at', '>=', $start)
            ->where('sm.occurred_at', '<', $end);
        StockMutationToko::join($query, 'sm');
        if (($tokoCondition = self::tokoCondition($filters)) !== '') {
            $query->whereRaw(substr($tokoCondition, 5));
        }

        return $query
            // -1 = mutasi bukan dari resi, 0 = resi tanpa toko.
            ->groupByRaw('CASE WHEN smt_resi.id IS NULL THEN -1 ELSE COALESCE(smt_resi.toko_id, 0) END')
            ->selectRaw('CASE WHEN smt_resi.id IS NULL THEN -1 ELSE COALESCE(smt_resi.toko_id, 0) END as toko_key')
            ->selectRaw("CASE WHEN MAX(smt_resi.id) IS NULL THEN 'Bukan dari resi (manual/lainnya)' ELSE COALESCE(MAX(smt_toko.name), 'Tanpa toko') END as toko")
            ->selectRaw('COALESCE(SUM(sm.qty), 0) as qty_out')
            ->selectRaw('COUNT(*) as frequency')
            ->selectRaw('COUNT(DISTINCT sm.item_id) as sku_count')
            ->selectRaw('COUNT(DISTINCT smt_resi.id) as resi_count')
            ->selectRaw('COUNT(DISTINCT DATE(sm.occurred_at)) as active_days')
            ->orderByDesc('qty_out')
            ->get();
    }

    public function ordered(array $filters): Builder
    {
        $query = $this->query($filters);
        if (($filters['tab'] ?? 'balance') === 'movement') {
            $query->orderByDesc('qty_out')->orderByDesc('outgoing_days');
        }

        return $query->orderBy('sku')->orderBy('id');
    }

    public function summary(array $filters): object
    {
        return DB::query()->fromSub($this->query($filters), 'summary_rows')
            ->selectRaw('COUNT(*) as total_sku, COALESCE(SUM(opening), 0) as opening, COALESCE(SUM(qty_in), 0) as qty_in, COALESCE(SUM(qty_out), 0) as qty_out, COALESCE(SUM(closing), 0) as closing')
            ->selectRaw("COUNT(CASE WHEN movement = 'fast' THEN 1 END) as fast, COUNT(CASE WHEN movement = 'medium' THEN 1 END) as medium, COUNT(CASE WHEN movement = 'slow' THEN 1 END) as slow, COUNT(CASE WHEN movement = 'non' THEN 1 END) as non")
            ->first();
    }

    public function dailyTrend(array $filters): array
    {
        $start = Carbon::parse($filters['date_from'])->startOfDay();
        $end = Carbon::parse($filters['date_to'])->addDay()->startOfDay();
        $table = $filters['stock_type'] === 'damaged' ? 'damaged_stock_mutations' : 'stock_mutations';
        $filteredItems = $this->query($filters)->select('id');

        $totals = DB::table($table.' as sm')
            ->joinSub($filteredItems, 'filtered_items', 'filtered_items.id', '=', 'sm.item_id')
            ->when(self::tokoCondition($filters) !== '', function ($query) use ($filters) {
                StockMutationToko::join($query, 'sm')->whereRaw(StockMutationToko::condition(self::tokoId($filters)));
            })
            ->where('sm.occurred_at', '>=', $start)
            ->where('sm.occurred_at', '<', $end)
            ->selectRaw('DATE(sm.occurred_at) as movement_date')
            ->selectRaw("SUM(CASE WHEN sm.direction = 'in' THEN sm.qty ELSE 0 END) as qty_in")
            ->selectRaw("SUM(CASE WHEN sm.direction = 'out' THEN sm.qty ELSE 0 END) as qty_out")
            ->groupByRaw('DATE(sm.occurred_at)')
            ->get()
            ->keyBy('movement_date');

        $dates = [];
        $qtyIn = [];
        $qtyOut = [];
        $cursor = $start->copy();
        $lastDate = $end->copy()->subDay();

        while ($cursor->lte($lastDate)) {
            $date = $cursor->toDateString();
            $daily = $totals->get($date);
            $dates[] = $date;
            $qtyIn[] = (int) ($daily->qty_in ?? 0);
            $qtyOut[] = (int) ($daily->qty_out ?? 0);
            $cursor->addDay();
        }

        return ['dates' => $dates, 'qty_in' => $qtyIn, 'qty_out' => $qtyOut];
    }
}
