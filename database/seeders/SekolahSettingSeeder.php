<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SekolahSettingSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('sekolah_setting')->updateOrInsert(
            ['id' => 1],
            [
                'tagline' => 'Mencetak Generasi Maju Dan Cerdas', // Sesuai gambar
                'logo' => null, // Dinullkan sesuai permintaan
                'pesan_selamat_datang' => 'Selamat datang di Sekolah Kita — tempat belajar dan berkarya.', // Sesuai gambar
                'buku_poin_path' => null, // Dinullkan sesuai permintaan
                'no_wa_kesiswaan' => null, // Dinullkan sesuai permintaan
                'updated_at' => now(),
            ]
        );
    }
}