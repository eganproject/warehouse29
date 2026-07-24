<?php

namespace App\Support;

use App\Models\Item;
use App\Models\ItemBundle;
use App\Models\ItemStock;
use App\Models\StockApiSyncRecord;
use Illuminate\Support\Carbon;

class StockApiSyncService
{
    public static function syncItem(int $itemId, ?Carbon $changedAt = null): void
    {
        $item = Item::with('category')->find($itemId);
        if (! $item) {
            return;
        }

        $qty = $item->is_bundle
            ? BundleService::getVirtualStock($item->id)
            : (int) (ItemStock::where('item_id', $item->id)->value('stock') ?? 0);

        StockApiSyncRecord::updateOrCreate(
            ['sku' => $item->sku],
            [
                'item_id' => $item->id,
                'name' => $item->name,
                'category' => $item->category?->name,
                'uom' => $item->uom ?: 'pcs',
                'qty' => max(0, $qty),
                'min_qty' => (int) $item->safety_stock > 0 ? $item->safety_stock : null,
                'status' => $item->is_active ? 'active' : 'inactive',
                'source_updated_at' => $changedAt ?? now(),
            ]
        );
    }

    public static function syncBundlesUsingComponent(int $componentItemId, ?Carbon $changedAt = null): void
    {
        ItemBundle::where('component_item_id', $componentItemId)
            ->pluck('bundle_item_id')
            ->unique()
            ->each(fn ($bundleId) => self::syncItem((int) $bundleId, $changedAt));
    }

    public static function syncItemsInCategory(int $categoryId, ?Carbon $changedAt = null): void
    {
        Item::where('category_id', $categoryId)->pluck('id')
            ->each(fn ($itemId) => self::syncItem((int) $itemId, $changedAt));
    }

    public static function markDeleted(Item $item, ?Carbon $changedAt = null): void
    {
        StockApiSyncRecord::updateOrCreate(
            ['sku' => $item->sku],
            [
                'item_id' => null,
                'name' => $item->name,
                'category' => $item->category?->name,
                'uom' => $item->uom ?: 'pcs',
                'qty' => 0,
                'min_qty' => null,
                'status' => 'deleted',
                'source_updated_at' => $changedAt ?? now(),
            ]
        );
    }
}
