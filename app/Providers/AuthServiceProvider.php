<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Models\Siswa;
use App\Models\Presensi;
use App\Models\PoinSiswa;
use App\Models\PresensiGuruMapel;
use App\Models\GuruMapel;
use App\Models\Orangtua;
use App\Models\Berita;
use App\Models\Pengumuman;
use App\Policies\PoinSiswaPolicy;
use App\Policies\PresensiPolicy;
use App\Policies\PresensiGuruMapelPolicy;
use App\Policies\GuruMapelPolicy;
use App\Policies\AccessControlPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        PoinSiswa::class         => PoinSiswaPolicy::class,
        Presensi::class          => PresensiPolicy::class,
        PresensiGuruMapel::class => PresensiGuruMapelPolicy::class,
        GuruMapel::class         => GuruMapelPolicy::class,
        Siswa::class             => AccessControlPolicy::class,
        Orangtua::class          => AccessControlPolicy::class,
        Berita::class            => AccessControlPolicy::class,
        Pengumuman::class        => AccessControlPolicy::class,
        User::class              => AccessControlPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(function (User $user, $ability) {
            if ($user->hasAnyRole(['admin', 'Admin', 'ADMIN'])) {
                return true;
            }
        });

        Gate::define('role', function (User $user, $role) {
            if (is_array($role)) {
                return $user->hasAnyRole($role);
            }
            if (is_string($role) && str_contains($role, ',')) {
                return $user->hasAnyRole(array_map('trim', explode(',', $role)));
            }
            return $user->hasRole((string) $role);
        });

        Gate::define('manage-users', fn(User $user) =>
            $user->hasAnyRole(['admin', 'Admin', 'ADMIN'])
        );

        Gate::define('manage-classes', fn(User $user) =>
            $user->hasAnyRole(['admin', 'Admin', 'ADMIN', 'guru', 'Guru'])
        );
    }
}
