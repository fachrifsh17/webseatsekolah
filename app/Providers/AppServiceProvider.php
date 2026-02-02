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
        // 1. Model dengan Policy Khusus
        $specialPolicies = [
            \App\Models\PoinSiswa::class         => \App\Policies\PoinSiswaPolicy::class,
            \App\Models\Presensi::class          => \App\Policies\PresensiPolicy::class,
            \App\Models\PresensiGuruMapel::class => \App\Policies\PresensiGuruMapelPolicy::class,
            \App\Models\GuruMapel::class         => \App\Policies\GuruMapelPolicy::class,
        ];

        foreach ($specialPolicies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // 2. Model Umum (Tetap menggunakan AccessControlPolicy)
        $generalModels = [
            \App\Models\Siswa::class,
            \App\Models\Orangtua::class,
            \App\Models\Berita::class,
            \App\Models\Pengumuman::class,
            \App\Models\JadwalProduktif::class,
            \App\Models\TahunAjaran::class,
            \App\Models\JamSekolah::class,
            \App\Models\SekolahSetting::class,
            \App\Models\User::class,
        ];

        foreach ($generalModels as $model) {
            Gate::policy($model, \App\Policies\AccessControlPolicy::class);
        }
    }
}
