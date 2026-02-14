<?php

namespace App\Policies;

use App\Models\Presensi;
use App\Models\User;

class PresensiPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Guru', 'Siswa', 'Orangtua'], ['Waka Kesiswaan', 'Kepala Sekolah']) 
            || $this->authorizeRoute($user, ['Admin']);
    }

    public function view(User $user, Presensi $presensi): bool
    {
        return $this->authorize($user, ['Guru', 'Siswa', 'Orangtua'], ['Waka Kesiswaan', 'Kepala Sekolah']) 
            || $this->authorizeRoute($user, ['Admin']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Guru'], ['Waka Kesiswaan']) 
            || $this->authorizeRoute($user, ['Admin']);
    }

    public function update(User $user, Presensi $presensi): bool
    {
        return $this->authorize($user, ['Guru'], ['Waka Kesiswaan']) 
            || $this->authorizeRoute($user, ['Admin']);
    }

    public function delete(User $user, Presensi $presensi): bool
    {
        return $this->authorize($user, [], ['Waka Kesiswaan']) 
            || $this->authorizeRoute($user, ['Admin']);
    }

    public function export(User $user): bool
    {
        return $this->authorize($user, ['Guru'], ['Waka Kesiswaan', 'Kepala Sekolah']) 
            || $this->authorizeRoute($user, ['Admin']);
    }

    protected function authorize(User $user, array $allowedRoles = [], array $allowedJabatans = []): bool
    {
        $hasRole = $user->roles->pluck('role_name')->intersect($allowedRoles)->isNotEmpty();

        $hasJabatan = $user->guruStaf
            ? $user->guruStaf->strukturJabatan
                ->map(fn($sj) => $sj->jabatan?->nama_jabatan)
                ->filter()
                ->intersect($allowedJabatans)
                ->isNotEmpty()
            : false;

        return $hasRole || $hasJabatan;
    }

    protected function authorizeRoute(User $user, array $allowedRoles = []): bool
    {
        $isAdmin = $user->roles->pluck('role_name')->intersect($allowedRoles)->isNotEmpty();

        if ($isAdmin && request()->is('api/admin/*')) {
            return true;
        }

        return false;
    }
}
