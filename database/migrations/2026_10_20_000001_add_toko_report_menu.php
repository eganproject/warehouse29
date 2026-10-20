<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $slug = 'report-toko';

    public function up(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        $parentId = DB::table('menus')->where('slug', 'reports')->value('id');
        if (! $parentId) {
            return;
        }

        // Hanya insert bila belum ada, agar perubahan menu dari web tidak tertimpa.
        if (! DB::table('menus')->where('slug', $this->slug)->exists()) {
            DB::table('menus')->insert([
                'name' => 'Laporan Toko',
                'slug' => $this->slug,
                'route' => 'admin.reports.toko.index',
                'icon' => 'fa-solid fa-shop',
                'parent_id' => $parentId,
                'sort_order' => 1.28,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('permission_menu')) {
            return;
        }

        $menuId = DB::table('menus')->where('slug', $this->slug)->value('id');
        $courierMenuId = DB::table('menus')->where('slug', 'report-couriers')->value('id');
        if (! $menuId || ! $courierMenuId) {
            return;
        }

        // Role yang bisa melihat Laporan Kurir otomatis bisa melihat Laporan Toko (view saja).
        $roleIds = DB::table('permission_menu')
            ->where('menu_id', $courierMenuId)
            ->where('can_view', true)
            ->pluck('role_id');
        $hasApprove = Schema::hasColumn('permission_menu', 'can_approve');

        foreach ($roleIds as $roleId) {
            $exists = DB::table('permission_menu')
                ->where('role_id', $roleId)
                ->where('menu_id', $menuId)
                ->exists();
            if ($exists) {
                continue;
            }

            $values = [
                'role_id' => $roleId,
                'menu_id' => $menuId,
                'can_view' => true,
                'can_create' => false,
                'can_update' => false,
                'can_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if ($hasApprove) {
                $values['can_approve'] = false;
            }

            DB::table('permission_menu')->insert($values);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        $menuId = DB::table('menus')->where('slug', $this->slug)->value('id');
        if ($menuId && Schema::hasTable('permission_menu')) {
            DB::table('permission_menu')->where('menu_id', $menuId)->delete();
        }
        DB::table('menus')->where('slug', $this->slug)->delete();
    }
};
