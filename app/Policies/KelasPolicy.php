<?php

namespace App\Policies;

use App\Models\Kelas;
use App\Models\User;

class KelasPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['waka-kesiswaan']);
    }

    public function view(User $user, Kelas $kelas): bool
    {
        return $this->authorize($user, ['Admin'], ['waka-kesiswaan']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['waka-kesiswaan']);
    }

    public function update(User $user, Kelas $kelas): bool
    {
        return $this->authorize($user, ['Admin'], ['waka-kesiswaan']);
    }

    public function delete(User $user, Kelas $kelas): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function restore(User $user, Kelas $kelas): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function forceDelete(User $user, Kelas $kelas): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    protected function authorize(User $user, array $allowedRoles = [], array $allowedJabatans = []): bool
    {
        $userRoles = $user->roles->pluck('role_name')->map(fn($r) => strtolower($r));
        $hasRole = $userRoles->intersect(array_map('strtolower', $allowedRoles))->isNotEmpty();

        $jabatanSlugs = $user->guruStaf
            ? $user->guruStaf->strukturJabatan
                ->map(fn($sj) => strtolower($sj->jabatan?->slug))
                ->filter()
                ->toArray()
            : [];

        $hasJabatan = !empty(array_intersect($jabatanSlugs, array_map('strtolower', $allowedJabatans)));

        return $hasRole || $hasJabatan;
    }
}