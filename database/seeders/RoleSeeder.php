<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['role_name' => 'Admin Sekolah', 'description' => 'Pengelola data sekolah'],
            ['role_name' => 'Guru', 'description' => 'Tenaga pendidik'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['role_name' => $role['role_name']],
                ['description' => $role['description']]
            );
        }
    }
}
