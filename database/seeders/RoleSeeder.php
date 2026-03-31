<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'Super Admin',
            'Admin Universitas',
            'Admin Fakultas',
            'Admin Prodi',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['nama_role' => $role]);
        }
    }
}
