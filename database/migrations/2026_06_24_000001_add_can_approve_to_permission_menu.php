<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('permission_menu', 'can_approve')) {
            Schema::table('permission_menu', function (Blueprint $table) {
                $table->boolean('can_approve')->default(false)->after('can_update');
            });

            DB::table('permission_menu')->update([
                'can_approve' => DB::raw('can_update'),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('permission_menu', 'can_approve')) {
            Schema::table('permission_menu', function (Blueprint $table) {
                $table->dropColumn('can_approve');
            });
        }
    }
};
