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
use App\Models\Semester; // Import Model Semester
use App\Models\Role;
use App\Models\MataPelajaran;
use App\Models\PoinSiswa;
use App\Models\Presensi;
use App\Models\PresensiGuruMapel;
use App\Models\GuruMapel;
use App\Models\Kelas;
use App\Models\Jurusan;
use App\Models\JamSekolah;
use App\Models\Ekstrakurikuler;
use App\Models\Fasilitas;
use App\Models\Album;
use App\Models\Banner;
use App\Models\DataKontak;
use App\Models\GuruStaf;
use App\Models\Jabatan;
use App\Models\KalenderAkademik;
use App\Models\Kurikulum;
use App\Models\LogAktivitas;
use App\Models\Media;
use App\Models\Pesan;
use App\Models\PortalSosmed;
use App\Models\PpdbLink;
use App\Models\PresensiSiswaDetail;
use App\Models\Prestasi as PrestasiModel;
use App\Models\RateLimits;
use App\Models\StrukturJabatan;
// Tambahan Import Model
use App\Models\KelasWaliKelas;
use App\Models\Tingkatan;

// Policies
use App\Policies\SiswaPolicy;
use App\Policies\OrangtuaPolicy;
use App\Policies\BeritaPolicy;
use App\Policies\PengumumanPolicy;
use App\Policies\PrestasiPolicy;
use App\Policies\SekolahSettingPolicy;
use App\Policies\ProfilSekolahPolicy;
use App\Policies\TahunAjaranPolicy;
use App\Policies\SemesterPolicy; // Import Policy Semester
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
use App\Policies\AlbumPolicy;
use App\Policies\BannerPolicy;
use App\Policies\DataKontakPolicy;
use App\Policies\GuruPolicy;
use App\Policies\JabatanPolicy;
use App\Policies\KalenderAkademikPolicy;
use App\Policies\KurikulumPolicy;
use App\Policies\LogAktivitasPolicy;
use App\Policies\MediaPolicy;
use App\Policies\PesanPolicy;
use App\Policies\PortalSosmedPolicy;
use App\Policies\PpdbLinkPolicy;
use App\Policies\PresensiSiswaDetailPolicy;
use App\Policies\StrukturJabatanPolicy;
// Tambahan Import Policy
use App\Policies\KelasWaliKelasPolicy;
use App\Policies\TingkatanPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class                => UserPolicy::class,
        Siswa::class               => SiswaPolicy::class,
        Orangtua::class            => OrangtuaPolicy::class,
        Berita::class              => BeritaPolicy::class,
        Pengumuman::class          => PengumumanPolicy::class,
        Prestasi::class            => PrestasiPolicy::class,
        SekolahSetting::class      => SekolahSettingPolicy::class,
        ProfilSekolah::class       => ProfilSekolahPolicy::class,
        TahunAjaran::class         => TahunAjaranPolicy::class,
        Semester::class            => SemesterPolicy::class, // Registrasi Policy Semester
        Role::class                => RolePolicy::class,
        MataPelajaran::class       => MapelPolicy::class,
        PoinSiswa::class           => PoinSiswaPolicy::class,
        Presensi::class            => PresensiPolicy::class,
        PresensiGuruMapel::class   => PresensiGuruMapelPolicy::class,
        GuruMapel::class           => GuruMapelPolicy::class,
        Kelas::class               => KelasPolicy::class,
        Jurusan::class             => JurusanPolicy::class,
        JamSekolah::class          => JamSekolahPolicy::class,
        Ekstrakurikuler::class     => EkstrakurikulerPolicy::class,
        Fasilitas::class           => FasilitasPolicy::class,
        Album::class               => AlbumPolicy::class,
        Banner::class              => BannerPolicy::class,
        DataKontak::class          => DataKontakPolicy::class,
        GuruStaf::class            => GuruPolicy::class,
        Jabatan::class             => JabatanPolicy::class,
        KalenderAkademik::class    => KalenderAkademikPolicy::class,
        Kurikulum::class           => KurikulumPolicy::class,
        LogAktivitas::class        => LogAktivitasPolicy::class,
        Media::class               => MediaPolicy::class,
        Pesan::class               => PesanPolicy::class,
        PortalSosmed::class        => PortalSosmedPolicy::class,
        PpdbLink::class            => PpdbLinkPolicy::class,
        StrukturJabatan::class     => StrukturJabatanPolicy::class,
        // Tambahan Model Policy
        KelasWaliKelas::class      => KelasWaliKelasPolicy::class,
        Tingkatan::class           => TingkatanPolicy::class,
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