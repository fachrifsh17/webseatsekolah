<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // $this->call digunakan untuk menjalankan seeder lain secara berurutan
        $this->call([
            RoleSeeder::class,           // 1. Isi Role dulu (R001, R002, dst)
            JabatanSeeder::class,        // 2. Isi Jabatan (Kepala Sekolah, Waka, dst)
            UserSeeder::class,           // 3. Baru isi User karena butuh ID dari Role
            ProfilSeeder::class,         // 4. Isi Profil Sekolah
            DataKontakSeeder::class,     // 5. Isi Data Kontak
            SekolahSettingSeeder::class, // 6. Isi Pengaturan Sekolah
        ]);
    }
}