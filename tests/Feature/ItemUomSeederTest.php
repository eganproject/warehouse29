<?php

namespace Tests\Feature;

use App\Models\Item;
use Database\Seeders\ItemUomSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemUomSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_only_fills_missing_uom_without_overwriting_existing_data(): void
    {
        $missing = Item::create(['sku' => 'UOM-MISSING', 'name' => 'Missing UOM', 'category_id' => 0, 'uom' => '']);
        $existing = Item::create(['sku' => 'UOM-BOX', 'name' => 'Box UOM', 'category_id' => 0, 'uom' => 'box']);

        $this->seed(ItemUomSeeder::class);

        $this->assertSame('pcs', $missing->fresh()->uom);
        $this->assertSame('box', $existing->fresh()->uom);
    }
}
