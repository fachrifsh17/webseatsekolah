<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role; // Pastikan namespace model Role sudah benar

class RoleSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            ['role_name' => 'Admin', 'description' => 'Akses penuh ke semua sistem'],
            ['role_name' => 'Guru', 'description' => 'Mengelola materi dan presensi'],
            ['role_name' => 'Siswa', 'description' => 'Melihat riwayat presensi dan materi'],
            ['role_name' => 'Orangtua', 'description' => 'Memantau kehadiran anak'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['role_name' => $role['role_name']], $role);
        }
    }
}