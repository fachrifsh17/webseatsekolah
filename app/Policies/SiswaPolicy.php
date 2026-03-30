<?php

namespace App\Policies;

use App\Models\Siswa;
use App\Models\User;

class SiswaPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kesiswaan', 'Ketua Jurusan', 'Wali Kelas', 'Kepala Sekolah']);
    }

    public function view(User $user, Siswa $siswa): bool
    {
        if ($this->authorize($user, ['Admin'], ['Waka Kesiswaan', 'Ketua Jurusan', 'Wali Kelas', 'Kepala Sekolah'])) {
            return true;
        }

        $userRoles = $user->roles->pluck('role_name');
        if ($userRoles->contains('Siswa')) {
            return $user->id === $siswa->user_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kesiswaan']);
    }

    public function update(User $user, Siswa $siswa): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kesiswaan']);
    }

    public function delete(User $user, Siswa $siswa): bool
    {
        return $this->authorize($user, ['Admin'], []);
    }

    public function restore(User $user, Siswa $siswa): bool
    {
        return $this->authorize($user, ['Admin'], []);
    }

    public function forceDelete(User $user, Siswa $siswa): bool
    {
        return $this->authorize($user, ['Admin'], []);
    }

    public function export(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kesiswaan', 'Ketua Jurusan', 'Wali Kelas', 'Kepala Sekolah']);
    }

    public function import(User $user): bool
    {
        return $this->authorize($user, ['Admin'], []);
    }

    public function importPreview(User $user): bool
    {
        return $this->authorize($user, ['Admin'], []);
    }

    public function bulkDestroy(User $user): bool
    {
        return $this->authorize($user, ['Admin'], []);
    }

    protected function authorize(User $user, array $allowedRoles = [], array $allowedJabatans = []): bool
    {
        $hasRole = $user->roles->pluck('role_name')->contains(function ($role) use ($allowedRoles) {
            return in_array($role, $allowedRoles);
        });

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