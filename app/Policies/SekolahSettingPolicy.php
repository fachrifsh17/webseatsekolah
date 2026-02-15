<?php

namespace App\Policies;

use App\Models\User;

class SekolahSettingPolicy
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
        // Cek apakah salah satu role user ada di dalam daftar allowedRoles
        $hasRole = $user->roles->pluck('role_name')->contains(function ($role) use ($allowedRoles) {
            return in_array($role, $allowedRoles);
        });

        // Cek apakah salah satu jabatan user ada di dalam daftar allowedJabatans
        $hasJabatan = false;
        if ($user->guruStaf) {
            $hasJabatan = $user->guruStaf->strukturJabatan
                ->map(fn($sj) => $sj->jabatan?->nama_jabatan)
                ->filter()
                ->contains(function ($jabatan) use ($allowedJabatans) {
                    return in_array($jabatan, $allowedJabatans);
                });
        }

        return $hasRole || $hasJabatan;
    }
}