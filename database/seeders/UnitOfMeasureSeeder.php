<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

class UnitOfMeasureSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'pcs' => 'Pieces', 'box' => 'Box', 'kg' => 'Kilogram',
            'liter' => 'Liter', 'roll' => 'Roll', 'pack' => 'Pack',
        ];

        $codes = collect($defaults)->keys()
            ->merge(Item::query()->whereNotNull('uom')->pluck('uom'))
            ->map(fn ($code) => strtolower(trim((string) $code)))
            ->filter()
            ->unique();

        foreach ($codes as $code) {
            UnitOfMeasure::firstOrCreate(
                ['code' => $code],
                ['name' => $defaults[$code] ?? strtoupper($code)]
            );
        }
    }
}
