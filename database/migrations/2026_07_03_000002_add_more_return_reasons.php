<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('return_reasons')) {
            return;
        }

        $now = now();
        $reasons = [
            ['code' => 'PRODUCTION_DEFECT', 'name' => 'Cacat produksi'],
            ['code' => 'MISSING_PRODUCTION_PART', 'name' => 'Kurang part produksi'],
            ['code' => 'COD_RETURN', 'name' => 'Return COD'],
            ['code' => 'NOT_AS_DESCRIBED', 'name' => 'Produk tidak sesuai deskripsi'],
            ['code' => 'WRONG_VARIANT', 'name' => 'Salah varian / warna / ukuran'],
            ['code' => 'DAMAGED_IN_TRANSIT', 'name' => 'Rusak saat pengiriman'],
            ['code' => 'CUSTOMER_REJECTED', 'name' => 'Ditolak penerima'],
            ['code' => 'LATE_DELIVERY', 'name' => 'Pengiriman terlambat'],
            ['code' => 'DUPLICATE_ORDER', 'name' => 'Duplikasi pesanan'],
            ['code' => 'NOT_FUNCTIONING', 'name' => 'Barang tidak berfungsi'],
            ['code' => 'EXPIRED_PRODUCT', 'name' => 'Produk kedaluwarsa'],
            ['code' => 'PACKAGING_ISSUE', 'name' => 'Masalah packaging'],
        ];

        foreach ($reasons as $reason) {
            $exists = DB::table('return_reasons')->where('code', $reason['code'])->exists();
            if ($exists) {
                DB::table('return_reasons')->where('code', $reason['code'])->update([
                    'name' => $reason['name'],
                    'is_active' => true,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('return_reasons')->insert([
                    'code' => $reason['code'],
                    'name' => $reason['name'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('return_reasons')) {
            return;
        }

        DB::table('return_reasons')
            ->whereIn('code', [
                'PRODUCTION_DEFECT',
                'MISSING_PRODUCTION_PART',
                'COD_RETURN',
                'NOT_AS_DESCRIBED',
                'WRONG_VARIANT',
                'DAMAGED_IN_TRANSIT',
                'CUSTOMER_REJECTED',
                'LATE_DELIVERY',
                'DUPLICATE_ORDER',
                'NOT_FUNCTIONING',
                'EXPIRED_PRODUCT',
                'PACKAGING_ISSUE',
            ])
            ->delete();
    }
};
