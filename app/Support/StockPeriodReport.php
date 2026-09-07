<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockPeriodReport
{
    public const MOVEMENTS = ['fast' => 'Fast moving', 'slow' => 'Slow moving', 'non' => 'Non-moving'];

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
        $mutations = DB::table($table)->select('item_id')
            ->where('occurred_at', '<', $end)
            ->selectRaw("SUM(CASE WHEN occurred_at < ? THEN CASE WHEN direction = 'in' THEN qty ELSE -qty END ELSE 0 END) as opening", [$start])
            ->selectRaw("SUM(CASE WHEN occurred_at >= ? AND direction = 'in' THEN qty ELSE 0 END) as qty_in", [$start])
            ->selectRaw("SUM(CASE WHEN occurred_at >= ? AND direction = 'out' THEN qty ELSE 0 END) as qty_out", [$start])
            ->selectRaw("SUM(CASE WHEN direction = 'in' THEN qty ELSE -qty END) as closing")
            ->selectRaw("COUNT(DISTINCT CASE WHEN occurred_at >= ? AND direction = 'out' AND qty > 0 THEN DATE(occurred_at) END) as outgoing_days", [$start])
            ->selectRaw("MAX(CASE WHEN direction = 'out' AND qty > 0 THEN occurred_at END) as last_out_at")
            ->groupBy('item_id');

        $items = DB::table('items as i')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->leftJoinSub($mutations, 'm', 'm.item_id', '=', 'i.id')
            ->when(isset($columns['is_active']), fn ($q) => $q->where('i.is_active', true))
            ->when(isset($columns['is_bundle']), fn ($q) => $q->where(fn ($nested) => $nested->where('i.is_bundle', false)->orWhereNull('i.is_bundle')))
            ->select('i.id', 'i.sku', 'i.name')
            ->selectRaw(isset($columns['address']) ? 'i.address' : "'' as address")
            ->selectRaw(isset($columns['uom']) ? 'i.uom' : "'' as uom")
            ->selectRaw(isset($columns['safety_stock']) ? 'i.safety_stock' : '0 as safety_stock')
            ->selectRaw("COALESCE(c.name, 'Tanpa Kategori') as category")
            ->selectRaw('COALESCE(m.opening, 0) as opening, COALESCE(m.qty_in, 0) as qty_in, COALESCE(m.qty_out, 0) as qty_out, COALESCE(m.closing, 0) as closing')
            ->selectRaw('COALESCE(m.outgoing_days, 0) as outgoing_days, m.last_out_at')
            ->selectRaw("CASE WHEN COALESCE(m.outgoing_days, 0) = 0 THEN 'non' WHEN m.outgoing_days >= ? THEN 'fast' ELSE 'slow' END as movement", [(int) ceil($this->days($filters) / 2)])
            ->selectRaw('COALESCE(m.qty_out, 0) * 1.0 / ? as average_out', [$this->days($filters)]);

        if (($filters['category_id'] ?? '') !== '') {
            $category = (int) $filters['category_id'];
            $items->where(function ($q) use ($category) {
                $q->where('i.category_id', $category);
                if ($category === 0) {
                    $q->orWhereNull('i.category_id');
                }
            });
        }
        $search = trim($filters['q'] ?? '');
        if ($search !== '') {
            $items->where(function ($q) use ($search, $columns) {
                $q->where('i.sku', 'like', "%{$search}%")->orWhere('i.name', 'like', "%{$search}%");
                if (isset($columns['address'])) {
                    $q->orWhere('i.address', 'like', "%{$search}%");
                }
            });
        }

        $query = DB::query()->fromSub($items, 'stock_report');
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
            ->selectRaw("COUNT(CASE WHEN movement = 'fast' THEN 1 END) as fast, COUNT(CASE WHEN movement = 'slow' THEN 1 END) as slow, COUNT(CASE WHEN movement = 'non' THEN 1 END) as non")
            ->first();
    }
}
