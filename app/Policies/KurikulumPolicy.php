<?php

namespace App\Policies;

use App\Models\Kurikulum;
use App\Models\User;

class KurikulumPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    public function view(User $user, Kurikulum $kurikulum): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    public function update(User $user, Kurikulum $kurikulum): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    public function delete(User $user, Kurikulum $kurikulum): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    public function restore(User $user, Kurikulum $kurikulum): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    public function forceDelete(User $user, Kurikulum $kurikulum): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    protected function authorize(User $user, array $allowedRoles = [], array $allowedJabatans = []): bool
    {
        // Mengecek apakah salah satu role user ada di dalam array allowedRoles
        $hasRole = $user->roles->contains(function ($role) use ($allowedRoles) {
            return in_array($role->role_name, $allowedRoles);
        });

        // Mengecek apakah salah satu jabatan user ada di dalam array allowedJabatans
        $hasJabatan = false;
        if ($user->guruStaf) {
            $hasJabatan = $user->guruStaf->strukturJabatan->contains(function ($sj) use ($allowedJabatans) {
                return in_array($sj->jabatan?->nama_jabatan, $allowedJabatans);
            });
        }

        return $hasRole || $hasJabatan;
    }
}