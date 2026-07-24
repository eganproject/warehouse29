<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Support\StockApiSyncService;
use Illuminate\Console\Command;

class BackfillStockApiSyncRecords extends Command
{
    protected $signature = 'stock-api:backfill {--chunk=200 : Number of items processed per batch}';

    protected $description = 'Build or refresh API stock synchronization records from current warehouse data';

    public function handle(): int
    {
        $count = 0;
        Item::query()->orderBy('id')->chunkById((int) $this->option('chunk'), function ($items) use (&$count) {
            foreach ($items as $item) {
                StockApiSyncService::syncItem($item->id, $item->updated_at ?? now());
                $count++;
            }
        });

        $this->info("{$count} record sinkronisasi API berhasil diperbarui.");

        return self::SUCCESS;
    }
}
