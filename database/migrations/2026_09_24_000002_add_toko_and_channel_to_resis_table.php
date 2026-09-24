<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Kolom nullable: resi lama tetap tanpa informasi toko/channel.
        Schema::table('resis', function (Blueprint $table) {
            if (!Schema::hasColumn('resis', 'toko_id')) {
                $table->foreignId('toko_id')
                    ->nullable()
                    ->after('kurir_id')
                    ->constrained('tokos')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('resis', 'channel_id')) {
                $table->foreignId('channel_id')
                    ->nullable()
                    ->after('toko_id')
                    ->constrained('channels')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('resis', function (Blueprint $table) {
            if (Schema::hasColumn('resis', 'channel_id')) {
                $table->dropConstrainedForeignId('channel_id');
            }
            if (Schema::hasColumn('resis', 'toko_id')) {
                $table->dropConstrainedForeignId('toko_id');
            }
        });
    }
};
