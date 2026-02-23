<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Administrator', 'slug' => 'admin', 'description' => 'Full access to system'],
            ['name' => 'User', 'slug' => 'user', 'description' => 'Standard user role'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $role['slug']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        // Seed basic menu structure
        $menuRows = [
            ['name' => 'Dashboard', 'slug' => 'dashboard', 'route' => 'admin.dashboard', 'icon' => 'fa-solid fa-gauge-high', 'parent_slug' => null, 'sort_order' => 0],
            ['name' => 'Master Data', 'slug' => 'master-data', 'route' => null, 'icon' => 'fa-solid fa-database', 'parent_slug' => null, 'sort_order' => 10],
            ['name' => 'Users', 'slug' => 'users', 'route' => 'admin.masterdata.users.index', 'icon' => 'fa-solid fa-users', 'parent_slug' => 'master-data', 'sort_order' => 20],
            ['name' => 'Roles', 'slug' => 'roles', 'route' => 'admin.masterdata.roles.index', 'icon' => 'fa-solid fa-user-shield', 'parent_slug' => 'master-data', 'sort_order' => 21],
            ['name' => 'Categories', 'slug' => 'categories', 'route' => 'admin.masterdata.categories.index', 'icon' => 'fa-solid fa-sitemap', 'parent_slug' => 'master-data', 'sort_order' => 21.5],
            ['name' => 'Items', 'slug' => 'items', 'route' => 'admin.masterdata.items.index', 'icon' => 'fa-solid fa-box', 'parent_slug' => 'master-data', 'sort_order' => 21.6],
            ['name' => 'Stores', 'slug' => 'stores', 'route' => 'admin.masterdata.stores.index', 'icon' => 'fa-solid fa-store', 'parent_slug' => 'master-data', 'sort_order' => 21.7],
            ['name' => 'Menus', 'slug' => 'menus', 'route' => 'admin.masterdata.menus.index', 'icon' => 'fa-solid fa-bars', 'parent_slug' => 'master-data', 'sort_order' => 22],
            ['name' => 'Permissions', 'slug' => 'permissions', 'route' => 'admin.masterdata.permissions.index', 'icon' => 'fa-solid fa-lock', 'parent_slug' => 'master-data', 'sort_order' => 23],
        ];

        // Insert parents first
        foreach ($menuRows as $menu) {
            if ($menu['parent_slug'] === null) {
                DB::table('menus')->updateOrInsert(
                    ['slug' => $menu['slug']],
                    [
                        'name' => $menu['name'],
                        'route' => $menu['route'],
                        'icon' => $menu['icon'],
                        'parent_id' => null,
                        'sort_order' => $menu['sort_order'],
                        'is_active' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        // Then children
        foreach ($menuRows as $menu) {
            if ($menu['parent_slug'] !== null) {
                $parent = DB::table('menus')->where('slug', $menu['parent_slug'])->first();
                DB::table('menus')->updateOrInsert(
                    ['slug' => $menu['slug']],
                    [
                        'name' => $menu['name'],
                        'route' => $menu['route'],
                        'icon' => $menu['icon'],
                        'parent_id' => $parent?->id,
                        'sort_order' => $menu['sort_order'],
                        'is_active' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        // Grant Admin full permissions to all menus
        $adminRole = DB::table('roles')->where('slug', 'admin')->first();
        if ($adminRole) {
            $menus = DB::table('menus')->get();
            foreach ($menus as $m) {
                DB::table('permission_menu')->updateOrInsert(
                    ['role_id' => $adminRole->id, 'menu_id' => $m->id],
                    [
                        'can_view' => true,
                        'can_create' => true,
                        'can_update' => true,
                        'can_delete' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }
}
