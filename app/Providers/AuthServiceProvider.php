<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

// Models
use App\Models\User;
use App\Models\Siswa;
use App\Models\Orangtua;
use App\Models\Berita;
use App\Models\Pengumuman;
use App\Models\Prestasi;
use App\Models\SekolahSetting;
use App\Models\ProfilSekolah;
use App\Models\TahunAjaran;
use App\Models\Role;
use App\Models\MataPelajaran;
use App\Models\PoinSiswa;
use App\Models\Presensi;
use App\Models\PresensiGuruMapel;
use App\Models\GuruMapel;
use App\Models\Kelas;
use App\Models\Jurusan;
use App\Models\JamSekolah;
use App\Models\JadwalProduktif;
use App\Models\Ekstrakurikuler;
use App\Models\Fasilitas;

// Policies
use App\Policies\SiswaPolicy;
use App\Policies\OrangtuaPolicy;
use App\Policies\BeritaPolicy;
use App\Policies\PengumumanPolicy;
use App\Policies\PrestasiPolicy;
use App\Policies\SekolahSettingPolicy;
use App\Policies\ProfilSekolahPolicy;
use App\Policies\TahunAjaranPolicy;
use App\Policies\RolePolicy;
use App\Policies\MapelPolicy;
use App\Policies\UserPolicy;
use App\Policies\PoinSiswaPolicy;
use App\Policies\PresensiPolicy;
use App\Policies\PresensiGuruMapelPolicy;
use App\Policies\GuruMapelPolicy;
use App\Policies\KelasPolicy;
use App\Policies\JurusanPolicy;
use App\Policies\JamSekolahPolicy;
use App\Policies\JadwalProduktifPolicy;
use App\Policies\EkstrakurikulerPolicy;
use App\Policies\FasilitasPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class              => UserPolicy::class,
        Siswa::class             => SiswaPolicy::class,
        Orangtua::class          => OrangtuaPolicy::class,
        Berita::class            => BeritaPolicy::class,
        Pengumuman::class        => PengumumanPolicy::class,
        Prestasi::class          => PrestasiPolicy::class,
        SekolahSetting::class    => SekolahSettingPolicy::class,
        ProfilSekolah::class     => ProfilSekolahPolicy::class,
        TahunAjaran::class       => TahunAjaranPolicy::class,
        Role::class              => RolePolicy::class,
        MataPelajaran::class     => MapelPolicy::class,
        PoinSiswa::class         => PoinSiswaPolicy::class,
        Presensi::class          => PresensiPolicy::class,
        PresensiGuruMapel::class => PresensiGuruMapelPolicy::class,
        GuruMapel::class         => GuruMapelPolicy::class,
        Kelas::class             => KelasPolicy::class,
        Jurusan::class           => JurusanPolicy::class,
        JamSekolah::class        => JamSekolahPolicy::class,
        JadwalProduktif::class   => JadwalProduktifPolicy::class,
        Ekstrakurikuler::class   => EkstrakurikulerPolicy::class,
        Fasilitas::class         => FasilitasPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // 1. Admin Bypass
        Gate::before(function (User $user) {
            if ($user->hasAnyRole(['admin', 'Admin', 'ADMIN'])) {
                return true;
            }
        });

        // 2. Gate Role Helper
        Gate::define('role', function (User $user, $role) {
            if (is_array($role)) {
                return $user->hasAnyRole($role);
            }
            if (is_string($role) && str_contains($role, ',')) {
                return $user->hasAnyRole(array_map('trim', explode(',', $role)));
            }
            return $user->hasRole((string) $role);
        });

        // 3. Custom Gates
        Gate::define('manage-users', fn(User $user) => 
            $user->hasAnyRole(['admin', 'Admin', 'ADMIN'])
        );

        Gate::define('manage-classes', fn(User $user) => 
            $user->hasAnyRole(['admin', 'Admin', 'ADMIN', 'guru', 'Guru'])
        );
    }
}