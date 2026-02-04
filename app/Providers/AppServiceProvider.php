<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $policies = [
            // --- Model Utama ---
            \App\Models\User::class              => \App\Policies\UserPolicy::class,
            \App\Models\Role::class              => \App\Policies\RolePolicy::class,
            \App\Models\Siswa::class             => \App\Policies\SiswaPolicy::class,
            \App\Models\Orangtua::class          => \App\Policies\OrangtuaPolicy::class,
            \App\Models\GuruMapel::class         => \App\Policies\GuruMapelPolicy::class,
            \App\Models\Jurusan::class           => \App\Policies\JurusanPolicy::class,
            \App\Models\Kelas::class             => \App\Policies\KelasPolicy::class,
            \App\Models\MataPelajaran::class     => \App\Policies\MapelPolicy::class,
            \App\Models\TahunAjaran::class       => \App\Policies\TahunAjaranPolicy::class,

            // --- Akademik & Jadwal ---
            \App\Models\JamSekolah::class        => \App\Policies\JamSekolahPolicy::class,
            \App\Models\JadwalProduktif::class   => \App\Policies\JadwalProduktifPolicy::class,

            // --- Konten & Informasi ---
            \App\Models\Berita::class            => \App\Policies\BeritaPolicy::class,
            \App\Models\Pengumuman::class        => \App\Policies\PengumumanPolicy::class,
            \App\Models\Prestasi::class          => \App\Policies\PrestasiPolicy::class,
            \App\Models\Ekstrakurikuler::class   => \App\Policies\EkstrakurikulerPolicy::class,
            \App\Models\Fasilitas::class         => \App\Policies\FasilitasPolicy::class,

            // --- Presensi & Poin ---
            \App\Models\PoinSiswa::class         => \App\Policies\PoinSiswaPolicy::class,
            \App\Models\Presensi::class          => \App\Policies\PresensiPolicy::class,
            \App\Models\PresensiGuruMapel::class => \App\Policies\PresensiGuruMapelPolicy::class,

            // --- Pengaturan & Sistem ---
            \App\Models\ProfilSekolah::class     => \App\Policies\ProfilSekolahPolicy::class,
            \App\Models\SekolahSetting::class    => \App\Policies\SekolahSettingPolicy::class,
        ];

        foreach ($policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}