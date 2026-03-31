<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'name' => 'Rita Rahmania',
                'email' => 'admin@tracer.local',
                'role' => 'Super Admin',
                'password' => 'admin123',
            ],
            [
                'name' => 'Budi Santoso',
                'email' => 'budi@kampus.ac.id',
                'role' => 'Admin Universitas',
                'password' => 'admin123',
            ],
            [
                'name' => 'Maya Putri',
                'email' => 'maya@fekon.ac.id',
                'role' => 'Admin Fakultas',
                'password' => 'admin123',
            ],
            [
                'name' => 'Dian Saputra',
                'email' => 'dian@si.ac.id',
                'role' => 'Admin Prodi',
                'password' => 'admin123',
            ],
        ];

        foreach ($defaults as $user) {
            $roleId = Role::where('nama_role', $user['role'])->value('id');

            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'role_id' => $roleId,
                    'password' => Hash::make($user['password']),
                ]
            );
        }
    }
}
