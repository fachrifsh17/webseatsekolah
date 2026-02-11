<?php

namespace App\Policies;

use App\Models\SekolahSetting;
use App\Models\User;

class SekolahSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Kepala Sekolah']);
    }

    public function view(User $user, SekolahSetting $sekolahSetting): bool
    {
        return $this->authorize($user, ['Admin'], ['Kepala Sekolah']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Kepala Sekolah']);
    }

    public function update(User $user, SekolahSetting $sekolahSetting): bool
    {
        return $this->authorize($user, ['Admin'], ['Kepala Sekolah']);
    }

    public function delete(User $user, SekolahSetting $sekolahSetting): bool
    {
        return $this->authorize($user, ['Admin'], ['Kepala Sekolah']);
    }

    public function restore(User $user, SekolahSetting $sekolahSetting): bool
    {
        return $this->authorize($user, ['Admin'], ['Kepala Sekolah']);
    }

    public function forceDelete(User $user, SekolahSetting $sekolahSetting): bool
    {
        return $this->authorize($user, ['Admin'], ['Kepala Sekolah']);
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
