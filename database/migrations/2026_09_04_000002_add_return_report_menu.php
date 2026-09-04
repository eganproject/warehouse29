<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        $parentId = DB::table('menus')->where('slug', 'reports')->value('id');
        if (! $parentId) {
            return;
        }

        DB::table('menus')->updateOrInsert(
            ['slug' => 'report-returns'],
            [
                'name' => 'Laporan Retur',
                'route' => 'admin.reports.returns.index',
                'icon' => 'fa-solid fa-arrow-right-arrow-left',
                'parent_id' => $parentId,
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        if (! Schema::hasTable('roles') || ! Schema::hasTable('permission_menu')) {
            return;
        }

        $menuId = DB::table('menus')->where('slug', 'report-returns')->value('id');
        $roleIds = DB::table('roles')
            ->whereIn('slug', ['superadmin', 'captain', 'admin-retur', 'admin-gudang', 'kepala-gudang'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            $values = [
                'can_view' => true,
                'can_create' => false,
                'can_update' => false,
                'can_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('permission_menu', 'can_approve')) {
                $values['can_approve'] = false;
            }

            DB::table('permission_menu')->updateOrInsert(
                ['role_id' => $roleId, 'menu_id' => $menuId],
                $values
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        $menuId = DB::table('menus')->where('slug', 'report-returns')->value('id');
        if ($menuId && Schema::hasTable('permission_menu')) {
            DB::table('permission_menu')->where('menu_id', $menuId)->delete();
        }
        DB::table('menus')->where('slug', 'report-returns')->delete();
    }
};
