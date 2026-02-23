<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            [
                'id' => 'R001', 
                'role_name' => 'Admin', 
                'description' => 'Akses penuh ke semua sistem'
            ],
            [
                'id' => 'R002', 
                'role_name' => 'Guru', 
                'description' => 'Mengelola materi dan presensi'
            ],
            [
                'id' => 'R003', 
                'role_name' => 'Siswa', 
                'description' => 'Melihat riwayat presensi dan materi'
            ],
            [
                'id' => 'R004', 
                'role_name' => 'Orangtua', 
                'description' => 'Memantau kehadiran anak'
            ],
        ];

        foreach ($roles as $role) {
            // Kita pakai DB::table agar lebih aman saat memasukkan ID string manual
            DB::table('roles')->updateOrInsert(
                ['id' => $role['id']], // Cari berdasarkan ID string
                [
                    'role_name' => $role['role_name'],
                    'description' => $role['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}