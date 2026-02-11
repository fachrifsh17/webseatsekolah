<?php

namespace App\Policies;

use App\Models\Prestasi;
use App\Models\User;

class PrestasiPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function view(User $user, Prestasi $prestasi): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function update(User $user, Prestasi $prestasi): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function delete(User $user, Prestasi $prestasi): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function restore(User $user, Prestasi $prestasi): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function forceDelete(User $user, Prestasi $prestasi): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
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
