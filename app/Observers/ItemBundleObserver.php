<?php

namespace App\Observers;

use App\Models\ItemBundle;
use App\Support\StockApiSyncService;

class ItemBundleObserver
{
    public function saved(ItemBundle $bundle): void
    {
        StockApiSyncService::syncItem($bundle->bundle_item_id, now());
    }

    public function deleted(ItemBundle $bundle): void
    {
        StockApiSyncService::syncItem($bundle->bundle_item_id, now());
    }
}
