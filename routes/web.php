<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\ItemStockController;
use App\Http\Controllers\Admin\InboundController;
use App\Http\Controllers\Admin\OutboundController;
use App\Http\Controllers\Admin\StockMutationController;
use App\Http\Controllers\Admin\StockOpnameController;
use App\Http\Controllers\Admin\DamagedGoodsController;
use App\Http\Controllers\Admin\PickerHistoryController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Picker\PickerSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// Basic health check route for debugging blank page on '/'
Route::get('/healthz', function () {
    return response('OK', 200);
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware('auth')->prefix('picker')->as('picker.')->group(function () {
    Route::get('/', [PickerSessionController::class, 'index'])->name('index');
    Route::get('/current', [PickerSessionController::class, 'current'])->name('current');
    Route::post('/start', [PickerSessionController::class, 'start'])->name('start');
    Route::get('/items/search', [PickerSessionController::class, 'searchItems'])->name('items.search');
    Route::post('/items', [PickerSessionController::class, 'storeItem'])->name('items.store');
    Route::put('/items/{id}', [PickerSessionController::class, 'updateItem'])->name('items.update');
    Route::delete('/items/{id}', [PickerSessionController::class, 'destroyItem'])->name('items.destroy');
    Route::post('/submit', [PickerSessionController::class, 'submit'])->name('submit');
});

require __DIR__.'/auth.php';

// Admin area
Route::middleware(['auth', 'verified', 'menu.permission'])->prefix('admin')->as('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('masterdata')->as('masterdata.')->group(function () {
        // Users DataTables
        Route::get('/users/data', [AdminUserController::class, 'data'])->name('users.data');
        // Users CRUD
        Route::resource('users', AdminUserController::class)->except(['show'])->names('users');

        // Roles DataTables
        Route::get('/roles/data', [RoleController::class, 'data'])->name('roles.data');
        // Roles CRUD
        Route::resource('roles', RoleController::class)->except(['show'])->names('roles');

        // Menus DataTables
        Route::get('/menus/data', [MenuController::class, 'data'])->name('menus.data');
        // Menus CRUD
        Route::resource('menus', MenuController::class)->except(['show'])->names('menus');

        // Categories (inheritance via parent)
        Route::get('/categories/data', [\App\Http\Controllers\Admin\CategoryController::class, 'data'])->name('categories.data');
        Route::resource('categories', \App\Http\Controllers\Admin\CategoryController::class)->except(['create','show','edit'])->names('categories');

        // Items
        Route::get('/items/data', [\App\Http\Controllers\Admin\ItemController::class, 'data'])->name('items.data');
        Route::resource('items', \App\Http\Controllers\Admin\ItemController::class)->except(['create','show','edit'])->names('items');
        Route::post('/items/import', [\App\Http\Controllers\Admin\ItemController::class, 'import'])->name('items.import');

        // Stores
        Route::get('/stores/data', [\App\Http\Controllers\Admin\StoreController::class, 'data'])->name('stores.data');
        Route::resource('stores', \App\Http\Controllers\Admin\StoreController::class)->except(['create','show','edit'])->names('stores');

        // Permissions management
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::get('/permissions/{role}/edit', [PermissionController::class, 'edit'])->name('permissions.edit');
        Route::put('/permissions/{role}', [PermissionController::class, 'update'])->name('permissions.update');
    });

    Route::prefix('inventory')->as('inventory.')->group(function () {
        // Item Stocks
        Route::get('/item-stocks', [ItemStockController::class, 'index'])->name('item-stocks.index');
        Route::get('/item-stocks/data', [ItemStockController::class, 'data'])->name('item-stocks.data');

        // Stock Mutations
        Route::get('/stock-mutations', [StockMutationController::class, 'index'])->name('stock-mutations.index');
        Route::get('/stock-mutations/data', [StockMutationController::class, 'data'])->name('stock-mutations.data');
        Route::get('/stock-mutations/{id}', [StockMutationController::class, 'show'])->name('stock-mutations.show');

        // Stock Opname
        Route::get('/stock-opname', [StockOpnameController::class, 'index'])->name('stock-opname.index');
        Route::get('/stock-opname/data', [StockOpnameController::class, 'data'])->name('stock-opname.data');
        Route::post('/stock-opname', [StockOpnameController::class, 'store'])->name('stock-opname.store');

        // Damaged Goods
        Route::get('/damaged-goods', [DamagedGoodsController::class, 'index'])->name('damaged-goods.index');
        Route::get('/damaged-goods/data', [DamagedGoodsController::class, 'data'])->name('damaged-goods.data');
        Route::post('/damaged-goods', [DamagedGoodsController::class, 'store'])->name('damaged-goods.store');
        Route::get('/damaged-goods/{id}', [DamagedGoodsController::class, 'show'])->name('damaged-goods.show');
        Route::put('/damaged-goods/{id}', [DamagedGoodsController::class, 'update'])->name('damaged-goods.update');
        Route::delete('/damaged-goods/{id}', [DamagedGoodsController::class, 'destroy'])->name('damaged-goods.destroy');
    });

    Route::prefix('inbound')->as('inbound.')->group(function () {
        Route::get('/receipts', [InboundController::class, 'receipts'])->name('receipts.index');
        Route::get('/receipts/data', [InboundController::class, 'receiptsData'])->name('receipts.data');
        Route::post('/receipts', [InboundController::class, 'receiptsStore'])->name('receipts.store');
        Route::get('/receipts/{id}', [InboundController::class, 'receiptsShow'])->name('receipts.show');
        Route::put('/receipts/{id}', [InboundController::class, 'receiptsUpdate'])->name('receipts.update');
        Route::delete('/receipts/{id}', [InboundController::class, 'receiptsDestroy'])->name('receipts.destroy');
        Route::get('/receipts/{id}/detail', [InboundController::class, 'receiptsDetail'])->name('receipts.detail');

        Route::get('/returns', [InboundController::class, 'returns'])->name('returns.index');
        Route::get('/returns/data', [InboundController::class, 'returnsData'])->name('returns.data');
        Route::post('/returns', [InboundController::class, 'returnsStore'])->name('returns.store');
        Route::get('/returns/{id}', [InboundController::class, 'returnsShow'])->name('returns.show');
        Route::put('/returns/{id}', [InboundController::class, 'returnsUpdate'])->name('returns.update');
        Route::delete('/returns/{id}', [InboundController::class, 'returnsDestroy'])->name('returns.destroy');
        Route::get('/returns/{id}/detail', [InboundController::class, 'returnsDetail'])->name('returns.detail');

        Route::get('/manuals', [InboundController::class, 'manuals'])->name('manuals.index');
        Route::get('/manuals/data', [InboundController::class, 'manualsData'])->name('manuals.data');
        Route::post('/manuals', [InboundController::class, 'manualsStore'])->name('manuals.store');
        Route::get('/manuals/{id}', [InboundController::class, 'manualsShow'])->name('manuals.show');
        Route::put('/manuals/{id}', [InboundController::class, 'manualsUpdate'])->name('manuals.update');
        Route::delete('/manuals/{id}', [InboundController::class, 'manualsDestroy'])->name('manuals.destroy');
        Route::get('/manuals/{id}/detail', [InboundController::class, 'manualsDetail'])->name('manuals.detail');
    });

    Route::prefix('outbound')->as('outbound.')->group(function () {
        Route::get('/pickers', [OutboundController::class, 'pickers'])->name('pickers.index');
        Route::get('/pickers/data', [OutboundController::class, 'pickersData'])->name('pickers.data');
        Route::post('/pickers', [OutboundController::class, 'pickersStore'])->name('pickers.store');
        Route::get('/pickers/{id}', [OutboundController::class, 'pickersShow'])->name('pickers.show');
        Route::put('/pickers/{id}', [OutboundController::class, 'pickersUpdate'])->name('pickers.update');
        Route::delete('/pickers/{id}', [OutboundController::class, 'pickersDestroy'])->name('pickers.destroy');
        Route::get('/pickers/{id}/detail', [OutboundController::class, 'pickersDetail'])->name('pickers.detail');

        Route::get('/manuals', [OutboundController::class, 'manuals'])->name('manuals.index');
        Route::get('/manuals/data', [OutboundController::class, 'manualsData'])->name('manuals.data');
        Route::post('/manuals', [OutboundController::class, 'manualsStore'])->name('manuals.store');
        Route::get('/manuals/{id}', [OutboundController::class, 'manualsShow'])->name('manuals.show');
        Route::put('/manuals/{id}', [OutboundController::class, 'manualsUpdate'])->name('manuals.update');
        Route::delete('/manuals/{id}', [OutboundController::class, 'manualsDestroy'])->name('manuals.destroy');
        Route::get('/manuals/{id}/detail', [OutboundController::class, 'manualsDetail'])->name('manuals.detail');

        Route::get('/returns', [OutboundController::class, 'returns'])->name('returns.index');
        Route::get('/returns/data', [OutboundController::class, 'returnsData'])->name('returns.data');
        Route::post('/returns', [OutboundController::class, 'returnsStore'])->name('returns.store');
        Route::get('/returns/{id}', [OutboundController::class, 'returnsShow'])->name('returns.show');
        Route::put('/returns/{id}', [OutboundController::class, 'returnsUpdate'])->name('returns.update');
        Route::delete('/returns/{id}', [OutboundController::class, 'returnsDestroy'])->name('returns.destroy');
        Route::get('/returns/{id}/detail', [OutboundController::class, 'returnsDetail'])->name('returns.detail');

        Route::get('/picker-sessions', [PickerHistoryController::class, 'index'])->name('picker-sessions.index');
        Route::get('/picker-sessions/data', [PickerHistoryController::class, 'data'])->name('picker-sessions.data');
    });
});
