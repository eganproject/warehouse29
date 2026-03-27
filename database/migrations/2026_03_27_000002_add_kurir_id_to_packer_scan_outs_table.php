<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('packer_scan_outs', function (Blueprint $table) {
            if (!Schema::hasColumn('packer_scan_outs', 'kurir_id')) {
                $table->foreignId('kurir_id')
                    ->nullable()
                    ->constrained('kurirs')
                    ->nullOnDelete()
                    ->after('resi_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('packer_scan_outs', function (Blueprint $table) {
            if (Schema::hasColumn('packer_scan_outs', 'kurir_id')) {
                $table->dropConstrainedForeignId('kurir_id');
            }
        });
    }
};
