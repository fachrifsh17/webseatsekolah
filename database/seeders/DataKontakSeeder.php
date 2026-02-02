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
                'alamat_lengkap' => 'Jl. Raya Bantarkalong No. 10, Tasikmalaya, Jawa Barat',
                'telepon' => '0265-123456',
                'email_resmi' => 'info@smancontoh.sch.id',
                'peta_embed_code' => '<iframe src="https://maps.google.com/..."></iframe>',
            ]
        );
    }
}
