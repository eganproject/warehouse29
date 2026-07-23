<?php

namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Seeder;

class ItemUomSeeder extends Seeder
{
    /** Fill only missing UOM values; never overwrite a value already set by a user. */
    public function run(): void
    {
        Item::query()
            ->where(function ($query) {
                $query->whereNull('uom')->orWhere('uom', '');
            })
            ->orderBy('id')
            ->chunkById(500, function ($items) {
                foreach ($items as $item) {
                    $item->update(['uom' => 'pcs']);
                }
            });
    }
}
