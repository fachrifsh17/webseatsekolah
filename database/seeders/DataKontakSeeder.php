<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon; // Tambahkan ini di bagian atas

class DataKontakSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('data_kontak')->updateOrInsert(
            ['id' => 1],
            [
                // Ubah alamat_lengkap menjadi struktur kolom yang benar
                'alamat_jalan' => 'Jl. Pendidikan No. 55',
                'desa_kelurahan' => 'Bantarkalong',
                'kecamatan' => 'Bantarkalong',
                'kabupaten_kota' => 'Tasikmalaya',
                'provinsi' => 'Jawa Barat',
                
                'telepon' => '0265-119382',
                'email_resmi' => 'info@sekolahkita.sch.id',
                'peta_embed_code' => '<iframe src=\'https://maps.google.com.smk/...\'></iframe>',
                // Gunakan Carbon untuk waktu yang lebih baik
                'updated_at' => Carbon::now(), 
            ]
        );
    }
}