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
        $models = [
            \App\Models\Presensi::class,
            \App\Models\PoinSiswa::class,
            \App\Models\Siswa::class,
            \App\Models\Orangtua::class, // Pastikan ini ada karena guru mengakses 'data-orangtua'
            \App\Models\Berita::class,
            \App\Models\Pengumuman::class,
            \App\Models\JadwalProduktif::class,
            \App\Models\TahunAjaran::class,
            \App\Models\JamSekolah::class,
            \App\Models\SekolahSetting::class, // Pastikan nama model sesuai (Setting atau SekolahSetting)
            \App\Models\User::class,
        ];

        foreach ($models as $model) {
            Gate::policy($model, \App\Policies\AccessControlPolicy::class);
        }
    }
}