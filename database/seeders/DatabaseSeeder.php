<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            JabatanSeeder::class,
            UserSeeder::class,
            ProfilSeeder::class,
            DataKontakSeeder::class,
            SekolahSettingSeeder::class,
            TingkatanSeeder::class, 
        ]);
    }
}