<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('return_reasons')) {
            Schema::create('return_reasons', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $now = now();
        $reasons = [
            ['code' => 'DAMAGED', 'name' => 'Barang rusak'],
            ['code' => 'WRONG_ITEM', 'name' => 'Salah barang'],
            ['code' => 'QTY_SHORT', 'name' => 'Qty kurang'],
            ['code' => 'INCOMPLETE_PACKAGE', 'name' => 'Paket tidak lengkap'],
            ['code' => 'CUSTOMER_CANCEL', 'name' => 'Customer batal'],
            ['code' => 'DELIVERY_FAILED', 'name' => 'Gagal kirim'],
            ['code' => 'ADDRESS_ISSUE', 'name' => 'Alamat bermasalah'],
            ['code' => 'EXCHANGE', 'name' => 'Tukar barang'],
            ['code' => 'OTHER', 'name' => 'Lainnya'],
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

        Schema::table('inbound_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('inbound_transactions', 'resi_id')) {
                $table->foreignId('resi_id')
                    ->nullable()
                    ->after('ref_no')
                    ->constrained('resis')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('inbound_transactions', 'return_resi_no')) {
                $table->string('return_resi_no', 100)->nullable()->after('resi_id');
            }
        });

        Schema::table('inbound_items', function (Blueprint $table) {
            if (!Schema::hasColumn('inbound_items', 'qty_resi')) {
                $table->integer('qty_resi')->default(0)->after('qty');
            }
            if (!Schema::hasColumn('inbound_items', 'qty_difference')) {
                $table->integer('qty_difference')->default(0)->after('qty_received');
            }
            if (!Schema::hasColumn('inbound_items', 'return_reason_id')) {
                $table->foreignId('return_reason_id')
                    ->nullable()
                    ->after('qty_damaged')
                    ->constrained('return_reasons')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('inbound_items', 'return_reason_note')) {
                $table->text('return_reason_note')->nullable()->after('return_reason_id');
            }
        });

        DB::table('inbound_items')
            ->where(function ($query) {
                $query->whereNull('qty_resi')->orWhere('qty_resi', 0);
            })
            ->update([
                'qty_resi' => DB::raw('COALESCE(qty_received, qty)'),
                'qty_difference' => 0,
            ]);
    }

    public function down(): void
    {
        Schema::table('inbound_items', function (Blueprint $table) {
            if (Schema::hasColumn('inbound_items', 'return_reason_note')) {
                $table->dropColumn('return_reason_note');
            }
            if (Schema::hasColumn('inbound_items', 'return_reason_id')) {
                $table->dropForeign(['return_reason_id']);
                $table->dropColumn('return_reason_id');
            }
            if (Schema::hasColumn('inbound_items', 'qty_difference')) {
                $table->dropColumn('qty_difference');
            }
            if (Schema::hasColumn('inbound_items', 'qty_resi')) {
                $table->dropColumn('qty_resi');
            }
        });

        Schema::table('inbound_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('inbound_transactions', 'return_resi_no')) {
                $table->dropColumn('return_resi_no');
            }
            if (Schema::hasColumn('inbound_transactions', 'resi_id')) {
                $table->dropForeign(['resi_id']);
                $table->dropColumn('resi_id');
            }
        });

        Schema::dropIfExists('return_reasons');
    }
};
