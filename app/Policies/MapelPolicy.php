<?php

namespace App\Policies;

use App\Models\MataPelajaran;
use App\Models\User;

class MapelPolicy
{
    // Gunakan ?MataPelajaran agar parameter kedua boleh kosong/null
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum', 'Ketua Jurusan']);
    }

    public function view(User $user, ?MataPelajaran $mataPelajaran = null): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum', 'Ketua Jurusan']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    public function update(User $user, ?MataPelajaran $mataPelajaran = null): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    public function delete(User $user, ?MataPelajaran $mataPelajaran = null): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    public function restore(User $user, ?MataPelajaran $mataPelajaran = null): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    public function forceDelete(User $user, ?MataPelajaran $mataPelajaran = null): bool
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

    public function importPreview(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    public function deleteBulk(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    protected function authorize(User $user, array $allowedRoles = [], array $allowedJabatans = []): bool
    {
        // Cek Role
        $hasRole = $user->roles->contains(function ($role) use ($allowedRoles) {
            return in_array($role->role_name, $allowedRoles);
        });

        // Cek Jabatan (Waka/Kajur)
        $hasJabatan = false;
        if ($user->guruStaf && $user->guruStaf->strukturJabatan) {
            $hasJabatan = $user->guruStaf->strukturJabatan->contains(function ($sj) use ($allowedJabatans) {
                return in_array($sj->jabatan?->nama_jabatan, $allowedJabatans);
            });
        }

        return $hasRole || $hasJabatan;
    }
}