<?php

namespace App\Policies;

use App\Models\MataPelajaran;
use App\Models\User;

class MapelPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum', 'Ketua Jurusan']);
    }

    public function view(User $user, MataPelajaran $mataPelajaran): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum', 'Ketua Jurusan']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    public function update(User $user, MataPelajaran $mataPelajaran): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    public function delete(User $user, MataPelajaran $mataPelajaran): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    public function restore(User $user, MataPelajaran $mataPelajaran): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    public function forceDelete(User $user, MataPelajaran $mataPelajaran): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    public function export(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum', 'Ketua Jurusan']);
    }

    public function import(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    /**
     * Logika otorisasi tanpa menggunakan intersect
     */
    protected function authorize(User $user, array $allowedRoles = [], array $allowedJabatans = []): bool
    {
        // Cek Role menggunakan contains (Collection Laravel)
        $hasRole = $user->roles->contains(function ($role) use ($allowedRoles) {
            return in_array($role->role_name, $allowedRoles);
        });

        // Cek Jabatan
        $hasJabatan = false;
        if ($user->guruStaf && $user->guruStaf->strukturJabatan) {
            $hasJabatan = $user->guruStaf->strukturJabatan->contains(function ($sj) use ($allowedJabatans) {
                return in_array($sj->jabatan?->nama_jabatan, $allowedJabatans);
            });
        }

        return $hasRole || $hasJabatan;
    }
}