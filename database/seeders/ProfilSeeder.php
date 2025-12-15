<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProfilSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('profil_sekolah')->updateOrInsert(
            ['id' => 1],
            [
                'sejarah' => 'Didirikan tahun 1985 sebagai sekolah unggulan di Bantarkalong.',
                'visi' => 'Menjadi sekolah berkarakter dan berprestasi.',
                'misi' => 'Meningkatkan kualitas pendidikan dan pengembangan karakter siswa.',
                'npsn' => '20234567',
                'akreditasi' => 'A',
                'sambutan_kepsek' => 'Selamat datang di SMA Negeri Bantarkalong, tempat tumbuhnya generasi emas Indonesia.',
            ]
        );
    }
}
