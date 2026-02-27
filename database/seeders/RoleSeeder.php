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
            ['name' => 'Picker', 'slug' => 'picker', 'description' => 'Picker mobile role'],
            ['name' => 'Packer', 'slug' => 'packer', 'description' => 'Packer mobile role'],
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
            ['name' => 'Inventory', 'slug' => 'inventory', 'route' => null, 'icon' => 'fa-solid fa-warehouse', 'parent_slug' => null, 'sort_order' => 12],
            ['name' => 'Inbound', 'slug' => 'inbound', 'route' => null, 'icon' => 'fa-solid fa-arrow-down', 'parent_slug' => null, 'sort_order' => 13],
            ['name' => 'Outbound', 'slug' => 'outbound', 'route' => null, 'icon' => 'fa-solid fa-arrow-up', 'parent_slug' => null, 'sort_order' => 14],
            ['name' => 'Users', 'slug' => 'users', 'route' => 'admin.masterdata.users.index', 'icon' => 'fa-solid fa-users', 'parent_slug' => 'master-data', 'sort_order' => 20],
            ['name' => 'Roles', 'slug' => 'roles', 'route' => 'admin.masterdata.roles.index', 'icon' => 'fa-solid fa-user-shield', 'parent_slug' => 'master-data', 'sort_order' => 21],
            ['name' => 'Categories', 'slug' => 'categories', 'route' => 'admin.masterdata.categories.index', 'icon' => 'fa-solid fa-sitemap', 'parent_slug' => 'master-data', 'sort_order' => 21.5],
            ['name' => 'Items', 'slug' => 'items', 'route' => 'admin.masterdata.items.index', 'icon' => 'fa-solid fa-box', 'parent_slug' => 'master-data', 'sort_order' => 21.6],
            ['name' => 'Item Stocks', 'slug' => 'item-stocks', 'route' => 'admin.inventory.item-stocks.index', 'icon' => 'fa-solid fa-boxes-stacked', 'parent_slug' => 'inventory', 'sort_order' => 10],
            ['name' => 'Stock Mutations', 'slug' => 'stock-mutations', 'route' => 'admin.inventory.stock-mutations.index', 'icon' => 'fa-solid fa-right-left', 'parent_slug' => 'inventory', 'sort_order' => 11],
            ['name' => 'Stock Opname', 'slug' => 'stock-opname', 'route' => 'admin.inventory.stock-opname.index', 'icon' => 'fa-solid fa-clipboard-check', 'parent_slug' => 'inventory', 'sort_order' => 12],
            ['name' => 'Penyesuaian Stok', 'slug' => 'stock-adjustments', 'route' => 'admin.inventory.stock-adjustments.index', 'icon' => 'fa-solid fa-sliders', 'parent_slug' => 'inventory', 'sort_order' => 12.5],
            ['name' => 'Barang Rusak', 'slug' => 'damaged-goods', 'route' => 'admin.inventory.damaged-goods.index', 'icon' => 'fa-solid fa-triangle-exclamation', 'parent_slug' => 'inventory', 'sort_order' => 13],
            ['name' => 'Stores', 'slug' => 'stores', 'route' => 'admin.masterdata.stores.index', 'icon' => 'fa-solid fa-store', 'parent_slug' => 'master-data', 'sort_order' => 21.7],
            ['name' => 'Menus', 'slug' => 'menus', 'route' => 'admin.masterdata.menus.index', 'icon' => 'fa-solid fa-bars', 'parent_slug' => 'master-data', 'sort_order' => 22],
            ['name' => 'Permissions', 'slug' => 'permissions', 'route' => 'admin.masterdata.permissions.index', 'icon' => 'fa-solid fa-lock', 'parent_slug' => 'master-data', 'sort_order' => 23],
            ['name' => 'Penerimaan Barang', 'slug' => 'inbound-receiving', 'route' => 'admin.inbound.receipts.index', 'icon' => 'fa-solid fa-dolly', 'parent_slug' => 'inbound', 'sort_order' => 10],
            ['name' => 'Retur', 'slug' => 'inbound-return', 'route' => 'admin.inbound.returns.index', 'icon' => 'fa-solid fa-rotate-left', 'parent_slug' => 'inbound', 'sort_order' => 11],
            ['name' => 'Manual', 'slug' => 'inbound-manual', 'route' => 'admin.inbound.manuals.index', 'icon' => 'fa-solid fa-pen-to-square', 'parent_slug' => 'inbound', 'sort_order' => 12],
            // ['name' => 'Picker', 'slug' => 'outbound-picker', 'route' => 'admin.outbound.pickers.index', 'icon' => 'fa-solid fa-people-carry-box', 'parent_slug' => 'outbound', 'sort_order' => 10],
            ['name' => 'Manual', 'slug' => 'outbound-manual', 'route' => 'admin.outbound.manuals.index', 'icon' => 'fa-solid fa-pen-to-square', 'parent_slug' => 'outbound', 'sort_order' => 11],
            ['name' => 'Retur', 'slug' => 'outbound-return', 'route' => 'admin.outbound.returns.index', 'icon' => 'fa-solid fa-rotate-left', 'parent_slug' => 'outbound', 'sort_order' => 12],
            ['name' => 'History Picker', 'slug' => 'outbound-picker-history', 'route' => 'admin.outbound.picker-sessions.index', 'icon' => 'fa-solid fa-clipboard-list', 'parent_slug' => 'outbound', 'sort_order' => 13],
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
