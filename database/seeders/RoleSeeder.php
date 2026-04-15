<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Administrator', 'slug' => 'admin', 'description' => 'Full access to system'],
            ['name' => 'User', 'slug' => 'user', 'description' => 'Standard user role'],
            ['name' => 'QC Scan', 'slug' => 'picker', 'description' => 'Role QC scan input barang ke QC transit'],
            ['name' => 'Packer (Legacy)', 'slug' => 'packer', 'description' => 'Role mobile lama untuk flow packing sebelumnya'],
            ['name' => 'Scan Out', 'slug' => 'admin-scan', 'description' => 'Role mobile khusus scan out'],
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
    }
}
