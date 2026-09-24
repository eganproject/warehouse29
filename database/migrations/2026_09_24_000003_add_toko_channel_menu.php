<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $slug = 'toko-channel';

    public function up(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        $parentId = DB::table('menus')->where('slug', 'master-data')->value('id');
        if (! $parentId) {
            return;
        }

        // Hanya insert bila belum ada, agar perubahan menu dari web tidak tertimpa.
        if (! DB::table('menus')->where('slug', $this->slug)->exists()) {
            DB::table('menus')->insert([
                'name' => 'Toko & Channel',
                'slug' => $this->slug,
                'route' => 'admin.masterdata.toko-channel.index',
                'icon' => 'fa-solid fa-shop',
                'parent_id' => $parentId,
                'sort_order' => 21.48,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('roles') || ! Schema::hasTable('permission_menu')) {
            return;
        }

        $menuId = DB::table('menus')->where('slug', $this->slug)->value('id');
        if (! $menuId) {
            return;
        }

        // [view, create, update, approve, delete]
        $grants = [
            'superadmin' => [true, true, true, true, true],
            'admin-gudang' => [true, true, true, true, false],
            'kepala-gudang' => [true, false, false, false, false],
        ];
        $hasApprove = Schema::hasColumn('permission_menu', 'can_approve');

        foreach ($grants as $roleSlug => [$view, $create, $update, $approve, $delete]) {
            $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');
            if (! $roleId) {
                continue;
            }

            // Jangan timpa permission yang sudah diatur dari web.
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
                'can_view' => $view,
                'can_create' => $create,
                'can_update' => $update,
                'can_delete' => $delete,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if ($hasApprove) {
                $values['can_approve'] = $approve;
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
