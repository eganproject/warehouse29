<?php

namespace App\Observers;

use App\Models\ItemStock;
use App\Support\StockApiSyncService;

class ItemStockObserver
{
    public function saved(ItemStock $stock): void
    {
        StockApiSyncService::syncItem($stock->item_id, now());
        StockApiSyncService::syncBundlesUsingComponent($stock->item_id, now());
    }

    public function deleted(ItemStock $stock): void
    {
        StockApiSyncService::syncItem($stock->item_id, now());
        StockApiSyncService::syncBundlesUsingComponent($stock->item_id, now());
    }
}
