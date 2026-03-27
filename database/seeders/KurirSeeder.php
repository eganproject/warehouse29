<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KurirSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('kurirs')) {
            return;
        }

        $name = 'Tidak ditemukan kurir';
        $now = now();

        $kurirId = DB::table('kurirs')->where('name', $name)->value('id');
        if (!$kurirId) {
            $kurirId = DB::table('kurirs')->insertGetId([
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('kurirs')->where('id', $kurirId)->update([
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasTable('resis') && Schema::hasColumn('resis', 'kurir_id')) {
            DB::table('resis')
                ->whereNull('kurir_id')
                ->update([
                    'kurir_id' => $kurirId,
                    'updated_at' => $now,
                ]);
        }

        if (Schema::hasTable('packer_scan_outs') && Schema::hasColumn('packer_scan_outs', 'kurir_id')) {
            DB::table('packer_scan_outs')
                ->whereNull('kurir_id')
                ->update([
                    'kurir_id' => $kurirId,
                    'updated_at' => $now,
                ]);
        }
    }
}
