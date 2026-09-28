<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $menuId = DB::table('menus')->where('slug', 'operations-dashboard')->value('id');
        if ($menuId) {
            DB::table('permission_menu')->where('menu_id', $menuId)->delete();
            DB::table('menus')->where('id', $menuId)->delete();
        }
    }

    public function down(): void
    {
        // Intentionally left blank. The operations dashboard route/controller surface was removed.
    }
};
