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
        // Mengecek Role menggunakan contains
        $hasRole = $user->roles->contains(function ($role) use ($allowedRoles) {
            return in_array($role->role_name, $allowedRoles);
        });

        // Mengecek Jabatan melalui relasi guruStaf
        $hasJabatan = false;
        if ($user->guruStaf && $user->guruStaf->strukturJabatan) {
            $hasJabatan = $user->guruStaf->strukturJabatan->contains(function ($sj) use ($allowedJabatans) {
                return $sj->jabatan && in_array($sj->jabatan->nama_jabatan, $allowedJabatans);
            });
        }

        return $hasRole || $hasJabatan;
    }
}