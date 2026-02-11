<?php

namespace App\Policies;

use App\Models\PresensiGuruMapel;
use App\Models\User;

class PresensiGuruMapelPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin', 'Guru'], ['Waka Kesiswaan', 'Kepala Sekolah']);
    }

    public function view(User $user, PresensiGuruMapel $presensi): bool
    {
        return $this->authorize($user, ['Admin', 'Guru'], ['Waka Kesiswaan', 'Kepala Sekolah']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin', 'Guru'], ['Waka Kesiswaan']);
    }

    public function update(User $user, PresensiGuruMapel $presensi): bool
    {
        return $this->authorize($user, ['Admin', 'Guru'], ['Waka Kesiswaan']);
    }

    public function delete(User $user, PresensiGuruMapel $presensi): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kesiswaan']);
    }

    public function export(User $user): bool
    {
        return $this->authorize($user, ['Admin', 'Guru'], ['Waka Kesiswaan', 'Kepala Sekolah']);
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
}
