<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create or update admin user.
        DB::table('users')->updateOrInsert(
            ['email' => 'superadmin@gmail.com'],
            [
                'name' => 'Administrator',
                'divisi_id' => $this->divisiId('tanpa divisi'),
                'password' => Hash::make('Password29!2'),
                'email_verified_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $admin = DB::table('users')->where('email', 'superadmin@gmail.com')->first();
        $adminRole = DB::table('roles')->where('slug', 'superadmin')->first();

        if ($admin && $adminRole) {
            DB::table('role_user')->updateOrInsert(
                ['role_id' => $adminRole->id, 'user_id' => $admin->id],
                []
            );
        }

        $users = [
            ['name' => 'idris', 'email' => 'idris@gmail.com', 'password' => '567890', 'role' => 'Captain', 'divisi' => 'Akrilik'],
            ['name' => 'nurfan', 'email' => 'nurfan@gmail.com', 'password' => '567891', 'role' => 'Captain', 'divisi' => 'Aksesoris'],
            ['name' => 'Dalton', 'email' => 'daltonqc@gmail.com', 'password' => 'D09128', 'role' => 'QC Scan', 'divisi' => 'Akrilik'],
            ['name' => 'Topic', 'email' => 'adminqc@gmail.com', 'password' => 'T01928', 'role' => 'QC Scan', 'divisi' => 'Akrilik'],
            ['name' => 'Arya', 'email' => 'arya@gmail.com', 'password' => 'A123098', 'role' => 'Packer', 'divisi' => 'Akrilik'],
            ['name' => 'Budi Mulia', 'email' => 'budi@gmail.com', 'password' => 'B345678', 'role' => 'Picker', 'divisi' => 'Akrilik'],
            ['name' => 'Abduljaba', 'email' => 'abduljabar@gmail.com', 'password' => 'Aj90202', 'role' => 'Picker', 'divisi' => 'Akrilik'],
            ['name' => 'Tezi Mei', 'email' => 'tezi@gmail.com', 'password' => 'T10987', 'role' => 'Picker', 'divisi' => 'Akrilik'],
            ['name' => 'Suherman', 'email' => 'suherman@gmail.com', 'password' => 'S908291', 'role' => 'Packer', 'divisi' => 'Akrilik'],
            ['name' => 'Ade', 'email' => 'ade@gmail.com', 'password' => 'AD71902', 'role' => 'Packer', 'divisi' => 'Akrilik'],
            ['name' => 'Billy', 'email' => 'billy@gmail.com', 'password' => 'Bi091234', 'role' => 'Packer', 'divisi' => 'Akrilik'],
            ['name' => 'AZIZ FARID MAULANA', 'email' => 'aziz@gmail.com', 'password' => 'AZ12093487', 'role' => 'Packer', 'divisi' => 'Akrilik'],
            ['name' => 'Arifilham', 'email' => 'arifilham@gmail.com', 'password' => 'Ar90879', 'role' => 'Packer', 'divisi' => 'Akrilik'],
            ['name' => 'Riyandi Aprilip', 'email' => 'riyandi@gmail.com', 'password' => 'R5612980', 'role' => 'Packer', 'divisi' => 'Akrilik'],
            ['name' => 'Riski', 'email' => 'riski@gmail.com', 'password' => 'RI1209873', 'role' => 'Packer', 'divisi' => 'Akrilik'],
            ['name' => 'Bily', 'email' => 'bily@gmail.com', 'password' => 'B92831', 'role' => 'Packer', 'divisi' => 'Akrilik'],
            ['name' => 'M  Chotib', 'email' => 'chotib@gmail.com', 'password' => 'CH128901', 'role' => 'QC Scan', 'divisi' => 'Akrilik'],
            ['name' => 'Lupi', 'email' => 'lupi@gmail.com', 'password' => 'L019191', 'role' => 'Picker', 'divisi' => 'Aksesoris'],
            ['name' => 'Pirman', 'email' => 'pirman@gmail.com', 'password' => 'Pir83012', 'role' => 'Packer', 'divisi' => 'Aksesoris'],
            ['name' => 'Pathur', 'email' => 'pathur@gmail.com', 'password' => 'Pat23910', 'role' => 'Packer', 'divisi' => 'Aksesoris'],
            ['name' => 'Dika', 'email' => 'dika@gmail.com', 'password' => 'D36781', 'role' => 'QC Scan', 'divisi' => 'Aksesoris'],
            ['name' => 'Angga', 'email' => 'angga@gmail.com', 'password' => 'ANG7810', 'role' => 'Packer', 'divisi' => 'Aksesoris'],
            ['name' => 'Muhammad Rizky', 'email' => 'rizky@gmail.com', 'password' => 'RIZ672192', 'role' => 'Picker', 'divisi' => 'Aksesoris'],
            ['name' => 'Rasya', 'email' => 'rasya@gmail.com', 'password' => 'RAS8273', 'role' => 'Picker', 'divisi' => 'Aksesoris'],
            ['name' => 'Elsa Lintang', 'email' => 'elsa@gmail.com', 'password' => 'ELS20987', 'role' => 'Picker', 'divisi' => 'Aksesoris'],
            ['name' => 'Trio', 'email' => 'trio@gmail.com', 'password' => 'TR409123', 'role' => 'QC Scan', 'divisi' => 'Aksesoris'],
            ['name' => 'Fikih', 'email' => 'fikih@gmail.com', 'password' => 'FIK012381', 'role' => 'Packer', 'divisi' => 'Aksesoris'],
            ['name' => 'Indra', 'email' => 'indra@gmail.com', 'password' => 'In567391', 'role' => 'Admin retur', 'divisi' => null],
            ['name' => 'Badru', 'email' => 'badru@gmail.com', 'password' => 'B1234567', 'role' => 'Kepala Gudang', 'divisi' => null],
            ['name' => 'Gabriel', 'email' => 'gabriel@gmail.com', 'password' => 'Gab123989', 'role' => 'Admin Gudang', 'divisi' => null],
            ['name' => 'Naufal', 'email' => 'naufal@gmail.com', 'password' => 'N12345', 'role' => 'Admin Resi', 'divisi' => null],
            ['name' => 'Syahrul', 'email' => 'syahrul@gmail.com', 'password' => 'S12890', 'role' => 'Scan Out', 'divisi' => null],
            ['name' => 'Kosim', 'email' => 'kosim@gmail.com', 'password' => 'K82729', 'role' => 'Scan Out', 'divisi' => null],
            ['name' => 'Seken', 'email' => 'seken@gmail.com', 'password' => '288931', 'role' => 'Scan Out', 'divisi' => null],
            ['name' => 'Fahri', 'email' => 'fahri@gmail.com', 'password' => 'F019283', 'role' => 'QC Scan', 'divisi' => null],
        ];

        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'divisi_id' => $this->divisiId($user['divisi']),
                    'password' => Hash::make($user['password']),
                    'email_verified_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $createdUser = DB::table('users')->where('email', $user['email'])->first();
            if ($createdUser) {
                $this->syncRoles($createdUser->id, $this->roleSlugs($user['role']));
            }
        }
    }

    private function divisiId(?string $name): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        return DB::table('divisis')
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($name))])
            ->value('id');
    }

    /**
     * The application still uses the legacy "picker" slug for QC/picking access.
     *
     * @return array<int,string>
     */
    private function roleSlugs(?string $role): array
    {
        return match (strtolower(trim((string) $role))) {
            'captain' => ['captain'],
            'qc scan', 'picker' => ['picker'],
            'packer' => ['packer'],
            'admin retur' => ['admin-retur'],
            'kepala gudang' => ['kepala-gudang'],
            'admin gudang' => ['admin-gudang'],
            default => [],
        };
    }

    /**
     * @param array<int,string> $slugs
     */
    private function syncRoles(int $userId, array $slugs): void
    {
        DB::table('role_user')->where('user_id', $userId)->delete();

        if (empty($slugs)) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('slug', $slugs)
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_user')->updateOrInsert(
                ['role_id' => $roleId, 'user_id' => $userId],
                []
            );
        }
    }
}
