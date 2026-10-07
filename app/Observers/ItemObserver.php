<?php

namespace App\Observers;

use App\Models\Item;
use App\Models\ItemBundle;
use Illuminate\Validation\ValidationException;
use App\Support\StockApiSyncService;

class ItemObserver
{
    /**
     * Komponen bundle aktif tidak boleh dinonaktifkan: QC hanya menerima item aktif, sehingga
     * resi bundle tersebut tidak akan bisa di-QC. Berlaku untuk form, bulk update, dan import.
     */
    public function updating(Item $item): void
    {
        if (!$item->isDirty('is_active') || $item->is_active) {
            return;
        }

        $bundleSkus = ItemBundle::query()
            ->join('items as b', 'b.id', '=', 'item_bundles.bundle_item_id')
            ->where('item_bundles.component_item_id', $item->id)
            ->where('b.is_active', true)
            ->orderBy('b.sku')
            ->pluck('b.sku');

        if ($bundleSkus->isNotEmpty()) {
            throw ValidationException::withMessages([
                'is_active' => "SKU {$item->sku} masih menjadi komponen bundle aktif ({$bundleSkus->implode(', ')}). "
                    .'Nonaktifkan atau ubah bundle tersebut terlebih dahulu.',
            ]);
        }
    }

    public function saved(Item $item): void
    {
        StockApiSyncService::syncItem($item->id, now());
        StockApiSyncService::syncBundlesUsingComponent($item->id, now());
    }

    public function deleting(Item $item): void
    {
        $item->loadMissing('category');
        StockApiSyncService::markDeleted($item, now());
    }
}
