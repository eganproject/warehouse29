<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockApiSyncRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockApiSyncRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_and_regular_stock_changes_are_recorded_for_api_sync(): void
    {
        $category = Category::create(['name' => 'Perlengkapan']);
        $item = Item::create([
            'sku' => 'API-SYNC-001',
            'name' => 'Barang API',
            'uom' => 'pcs',
            'category_id' => $category->id,
            'safety_stock' => 5,
        ]);
        ItemStock::create(['item_id' => $item->id, 'stock' => 12]);

        $record = StockApiSyncRecord::where('sku', $item->sku)->firstOrFail();
        $this->assertSame('active', $record->status);
        $this->assertSame('Perlengkapan', $record->category);
        $this->assertSame('12.000', $record->qty);
        $this->assertSame('5.000', $record->min_qty);

        $item->update(['name' => 'Barang API Baru', 'is_active' => false]);
        $record->refresh();
        $this->assertSame('Barang API Baru', $record->name);
        $this->assertSame('inactive', $record->status);
    }

    public function test_deleted_item_is_kept_as_api_tombstone(): void
    {
        $item = Item::create([
            'sku' => 'API-DELETED-001',
            'name' => 'Barang Hapus',
            'uom' => 'pcs',
        ]);

        $item->delete();

        $record = StockApiSyncRecord::where('sku', 'API-DELETED-001')->firstOrFail();
        $this->assertSame('deleted', $record->status);
        $this->assertNull($record->item_id);
        $this->assertSame('0.000', $record->qty);
    }
}
