<?php

namespace App\Policies;

use App\Models\User;

class ProfilSekolahPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Kepala Sekolah']);
    }

    public function view(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Kepala Sekolah']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Kepala Sekolah']);
    }

    public function update(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Kepala Sekolah']);
    }

    public function delete(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Kepala Sekolah']);
    }

    public function restore(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Kepala Sekolah']);
    }

    public function forceDelete(User $user): bool
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
