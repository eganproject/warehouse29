<?php

namespace App\Observers;

use App\Models\Item;
use App\Support\StockApiSyncService;

class ItemObserver
{
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
