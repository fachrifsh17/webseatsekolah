<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $roleMap = DB::table('roles')->pluck('id', 'role_name');

        $users = [
            [
                'username' => 'adminsekolah',
                'password' => Hash::make('admin123'),
                'nama_lengkap' => 'Admin Sekolah',
                'role_name' => 'Admin Sekolah',
            ],
            [
                'username' => 'gurukimia',
                'password' => Hash::make('guru123'),
                'nama_lengkap' => 'Guru Kimia',
                'role_name' => 'Guru',
            ],
        ];

        foreach ($users as $user) {
            $roleId = $roleMap[$user['role_name']] ?? null;

            DB::table('users_admin')->updateOrInsert(
                ['username' => $user['username']],
                [
                    'password' => $user['password'],
                    'nama_lengkap' => $user['nama_lengkap'],
                    'role_id' => $roleId,
                    'created_at' => now(),
                ]
            );
        }
    }
}
