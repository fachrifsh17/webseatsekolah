<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DataKontakSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('data_kontak')->updateOrInsert(
            ['id' => 1],
            [
                'alamat_lengkap' => 'Jl. Pendidikan No. 55, Bantarkalong, Tasikmalaya, Jawa Barat',
                'telepon' => '0265-119382',
                'email_resmi' => 'info@sekolahkita.sch.id',
                // Menggunakan kode embed asli dari Google Maps sesuai gambar
                'peta_embed_code' => '<iframe src=\'https://maps.google.com.smk/...\'></iframe>',
                'updated_at' => '2026-01-01 22:24:13',
            ]
        );
    }
}