<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_bundle');
            }
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (Schema::hasColumn('items', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
