<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Retur exception picking list: barang yang sudah di-QC lalu dikembalikan ke stok.
        // Mengurangi qty "sudah diambil" picking list pada tanggal tersebut.
        Schema::create('picking_list_returns', function (Blueprint $table) {
            $table->id();
            $table->date('list_date');
            $table->string('sku', 100);
            $table->integer('qty');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['list_date', 'sku']);
        });

        // Riwayat retur sebelumnya hanya tercatat di mutasi stok; tanggal mutasi dipakai sebagai tanggal picking list.
        $rows = DB::table('stock_mutations as m')
            ->join('items as i', 'i.id', '=', 'm.item_id')
            ->where('m.source_type', 'picking_exception')
            ->where('m.source_subtype', 'return')
            ->where('m.direction', 'in')
            ->where('i.is_bundle', false)
            ->whereColumn('i.sku', 'm.source_code')
            ->get(['m.occurred_at', 'm.source_code', 'm.qty', 'm.created_by', 'm.created_at']);

        foreach ($rows as $row) {
            DB::table('picking_list_returns')->insert([
                'list_date' => substr((string) $row->occurred_at, 0, 10),
                'sku' => $row->source_code,
                'qty' => (int) $row->qty,
                'created_by' => $row->created_by,
                'created_at' => $row->created_at,
                'updated_at' => $row->created_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('picking_list_returns');
    }
};
