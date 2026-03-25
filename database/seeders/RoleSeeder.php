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
            ['name' => 'Picker', 'slug' => 'picker', 'description' => 'Picker mobile role'],
            ['name' => 'Packer', 'slug' => 'packer', 'description' => 'Packer mobile role'],
            ['name' => 'Admin Scan', 'slug' => 'admin-scan', 'description' => 'Scan out only mobile role'],
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
