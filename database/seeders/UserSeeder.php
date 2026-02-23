<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Ambil mapping role ID berdasarkan role_name
        $roleMap = DB::table('roles')->pluck('id', 'role_name');

        $users = [
            [
                'id' => 'USR001', // ID manual varchar(10)
                'username' => 'adminsekolah',
                'password' => Hash::make('admin123'),
                'role_name' => 'Admin',
            ],
            [
                'id' => 'USR002',
                'username' => 'gurukimia',
                'password' => Hash::make('guru123'),
                'role_name' => 'Guru',
            ],
        ];

        foreach ($users as $user) {
            // Cari role_id dari tabel roles
            $roleId = $roleMap[$user['role_name']] ?? null;

            // 1. Insert ke tabel users (sesuai struktur migration kamu)
            DB::table('users')->updateOrInsert(
                ['username' => $user['username']],
                [
                    'id' => $user['id'],
                    'password' => $user['password'],
                    'current_role' => $user['role_name'],
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // 2. Hubungkan ke tabel user_roles (Many-to-Many pivot)
            if ($roleId) {
                DB::table('user_roles')->updateOrInsert(
                    [
                        'user_id' => $user['id'],
                        'role_id' => $roleId
                    ],
                    ['created_at' => now()]
                );
            }
        }
    }
}