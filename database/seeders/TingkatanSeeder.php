<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TingkatanSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['id' => 'X', 'nama_tingkatan' => 'Sepuluh', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'XI', 'nama_tingkatan' => 'Sebelas', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'XII', 'nama_tingkatan' => 'Dua Belas', 'created_at' => now(), 'updated_at' => now()],
        ];

        DB::table('tingkatan')->insert($data);
    }
}