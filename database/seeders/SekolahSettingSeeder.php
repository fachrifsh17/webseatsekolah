<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SekolahSettingSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('sekolah_seting')->updateOrInsert(
            ['id' => 1],
            [
                'tagline' => 'Sekolah Unggul, Berkarakter, Berprestasi',
                'logo' => 'logo-sekolah.png',
                'pesan_selamat_datang' => 'Selamat datang di portal resmi SMA Negeri Bantarkalong.',
            ]
        );
    }
}
