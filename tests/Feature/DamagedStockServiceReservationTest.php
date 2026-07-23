<?php

namespace Tests\Feature;

use App\Models\DamagedItemStock;
use App\Models\Item;
use App\Support\DamagedStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DamagedStockServiceReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_opname_adjustment_can_preserve_damaged_stock_reservation(): void
    {
        $item = Item::create([
            'sku' => 'SKU-OPNAME-RUSAK',
            'name' => 'Item Opname Rusak',
            'category_id' => 0,
        ]);
        DamagedItemStock::create([
            'item_id' => $item->id,
            'stock' => 10,
            'reserved_stock' => 3,
        ]);

        DamagedStockService::mutate([
            'item_id' => $item->id,
            'direction' => 'out',
            'qty' => 2,
            'source_type' => 'opname',
            'source_subtype' => 'damaged',
            'source_id' => 1,
            'source_code' => 'OPN-TEST',
            'preserve_reservation' => true,
            'idempotency_key' => 'opname-damaged-test',
        ]);

        $stock = DamagedItemStock::where('item_id', $item->id)->firstOrFail();
        $this->assertSame(8, (int) $stock->stock);
        $this->assertSame(3, (int) $stock->reserved_stock);
    }
}
