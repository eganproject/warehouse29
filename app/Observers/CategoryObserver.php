<?php

namespace App\Observers;

use App\Models\Category;
use App\Support\StockApiSyncService;

class CategoryObserver
{
    public function saved(Category $category): void
    {
        StockApiSyncService::syncItemsInCategory($category->id, now());
    }
}
