<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Item;
use App\Models\ItemBundle;
use App\Models\ItemStock;
use App\Observers\CategoryObserver;
use App\Observers\ItemBundleObserver;
use App\Observers\ItemObserver;
use App\Observers\ItemStockObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Item::observe(ItemObserver::class);
        ItemStock::observe(ItemStockObserver::class);
        ItemBundle::observe(ItemBundleObserver::class);
        Category::observe(CategoryObserver::class);
    }
}
