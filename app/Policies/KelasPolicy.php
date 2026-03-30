<?php

namespace App\Policies;

use App\Models\Kelas;
use App\Models\User;

class KelasPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['waka-kesiswaan', 'ketua-jurusan']);
    }

    public function view(User $user, Kelas $kelas): bool
    {
        return $this->authorize($user, ['Admin'], ['waka-kesiswaan', 'ketua-jurusan']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['waka-kesiswaan']);
    }

    public function update(User $user, Kelas $kelas): bool
    {
        return $this->authorize($user, ['Admin'], ['waka-kesiswaan']);
    }

    public function delete(User $user, ?Kelas $kelas = null): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function import(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    protected function authorize(User $user, array $allowedRoles = [], array $allowedJabatans = []): bool
    {
        $allowedRoles = array_map('strtolower', $allowedRoles);
        $allowedJabatans = array_map('strtolower', $allowedJabatans);

        $hasRole = $user->roles->pluck('role_name')
            ->map(fn($role) => strtolower($role))
            ->contains(fn($role) => in_array($role, $allowedRoles));

        if ($hasRole) return true;

        if ($user->guruStaf) {
            return $user->guruStaf->strukturJabatan
                ->map(fn($sj) => strtolower($sj->jabatan?->slug))
                ->filter()
                ->contains(fn($slug) => in_array($slug, $allowedJabatans));
        }

        return false;
    }
}