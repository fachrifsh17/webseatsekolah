<?php

namespace App\Policies;

use App\Models\Siswa;
use App\Models\User;

class SiswaPolicy
{
    public function viewAny(User $user): bool
    {
        // Tambahkan 'Kepala Sekolah' di sini
        return $this->authorize($user, ['Admin'], ['Waka Kesiswaan', 'Ketua Jurusan', 'Wali Kelas', 'Kepala Sekolah']);
    }

    public function view(User $user, Siswa $siswa): bool
    {
        // Tambahkan 'Kepala Sekolah' di sini
        if ($this->authorize($user, ['Admin'], ['Waka Kesiswaan', 'Ketua Jurusan', 'Wali Kelas', 'Kepala Sekolah'])) {
            return true;
        }

        // Logic untuk Siswa melihat dirinya sendiri
        $userRoles = $user->roles->pluck('role_name');
        if ($userRoles->contains('Siswa')) {
            return $user->id === $siswa->user_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        // Admin bisa buat di mana saja, Waka Kesiswaan juga diberi izin (opsional)
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
        // Tambahkan 'Kepala Sekolah' agar bisa download laporan siswa
        return $this->authorize($user, ['Admin'], ['Waka Kesiswaan', 'Ketua Jurusan', 'Wali Kelas', 'Kepala Sekolah']);
    }

    public function import(User $user): bool
    {
        return $this->authorize($user, ['Admin'], []);
    }

    /**
     * Helper authorize yang diseragamkan (contains)
     */
    protected function authorize(User $user, array $allowedRoles = [], array $allowedJabatans = []): bool
    {
        // 1. Cek Role
        $hasRole = $user->roles->pluck('role_name')->contains(function ($role) use ($allowedRoles) {
            return in_array($role, $allowedRoles);
        });

        // 2. Cek Jabatan
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