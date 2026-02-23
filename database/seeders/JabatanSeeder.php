<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JabatanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('jabatans')->insert([
            [
                'id' => 1,
                'nama_jabatan' => 'Kepala Sekolah',
                'slug' => 'kepala-sekolah',
                'keterangan' => 'Akses profil, sambutan, dan semua data akademik',
                'created_at' => '2026-01-12 13:58:01',
                'updated_at' => '2026-01-12 13:58:01',
            ],
            [
                'id' => 2,
                'nama_jabatan' => 'Waka Kurikulum',
                'slug' => 'waka-kurikulum',
                'keterangan' => 'Akses kurikulum, kalender akademik, jadwal produktif, rincian minggu efektif',
                'created_at' => '2026-01-12 13:58:01',
                'updated_at' => '2026-01-12 13:58:01',
            ],
            [
                'id' => 3,
                'nama_jabatan' => 'Waka Kesiswaan',
                'slug' => 'waka-kesiswaan',
                'keterangan' => 'Akses presensi, poin siswa, ekstrakurikuler, beasiswa, dan tata tertib',
                'created_at' => '2026-01-12 13:58:01',
                'updated_at' => '2026-01-12 13:58:01',
            ],
            [
                'id' => 4,
                'nama_jabatan' => 'Waka Sarpras',
                'slug' => 'waka-sarpras',
                'keterangan' => 'Akses fasilitas, media, inventaris sekolah dan pemeliharaan gedung',
                'created_at' => '2026-01-12 13:58:01',
                'updated_at' => '2026-01-12 13:58:01',
            ],
            [
                'id' => 5,
                'nama_jabatan' => 'Waka Humas',
                'slug' => 'waka-humas',
                'keterangan' => 'Akses portal, PPDB link, banner/pengumuman, dan hubungan industri',
                'created_at' => '2026-01-12 13:58:01',
                'updated_at' => '2026-01-12 13:58:01',
            ],
            [
                'id' => 6,
                'nama_jabatan' => 'Ketua Jurusan',
                'slug' => 'ketua-jurusan',
                'keterangan' => 'Akses jadwal produktif dan kurikulum spesifik jurusan masing-masing',
                'created_at' => '2026-01-12 13:58:01',
                'updated_at' => '2026-01-12 13:58:01',
            ],
        ]);
    }
}