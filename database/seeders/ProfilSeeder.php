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
                'nama_sekolah' => 'SMKN 1 BANTARKALONG',
                'sejarah' => 'SMK Negeri 1 Bantarkalong berdiri sejak tahun 1995 sebagai lembaga pendidikan kejuruan unggulan.',
                'visi' => 'Menjadi sekolah unggulan yang menghasilkan lulusan berkarakter, kompeten, dan berdaya saing global.',
                'misi' => "1. Menyelenggarakan pendidikan berkualitas.\n2. Mengembangkan karakter siswa melalui disiplin.\n3. Memperkuat hubungan industri.",
                'npsn' => '20251234',
                'akreditasi' => 'A',
                'guru_staf_id' => null, // Dikosongkan sementara agar tidak error relasi
                'sambutan_kepsek' => 'Selamat datang di SMK Negeri 1 Bantarkalong. Mari bersama mewujudkan generasi emas Indonesia yang kompeten di bidangnya.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}