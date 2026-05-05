<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('damaged_item_stocks', function (Blueprint $table) {
            $table->integer('reserved_stock')->default(0)->after('stock');
        });

        // Backfill reserved_stock dari alokasi pending yang sudah ada
        DB::statement("
            UPDATE damaged_item_stocks dis
            INNER JOIN (
                SELECT dai.item_id, SUM(dai.qty) AS total
                FROM damaged_allocation_items dai
                INNER JOIN damaged_allocations da ON da.id = dai.damaged_allocation_id
                WHERE da.status = 'pending'
                GROUP BY dai.item_id
            ) sub ON sub.item_id = dis.item_id
            SET dis.reserved_stock = sub.total
        ");
    }

    public function down(): void
    {
        Schema::table('damaged_item_stocks', function (Blueprint $table) {
            $table->dropColumn('reserved_stock');
        });
    }
};
