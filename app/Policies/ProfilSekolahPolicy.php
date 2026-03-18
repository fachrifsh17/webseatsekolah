<?php

namespace App\Policies;

use App\Models\User;

class ProfilSekolahPolicy
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

    /**
     * Helper untuk validasi multi-role dan multi-jabatan
     */
    protected function authorize(User $user, array $allowedRoles = [], array $allowedJabatans = []): bool
    {
        // Pengecekan Role
        // Kita ambil semua nama role, lalu cek apakah ada salah satu yang diizinkan
        $hasRole = $user->roles->pluck('role_name')->contains(function ($value) use ($allowedRoles) {
            return in_array($value, $allowedRoles);
        });

        // Pengecekan Jabatan
        $hasJabatan = false;
        if ($user->guruStaf && $user->guruStaf->strukturJabatan) {
            $hasJabatan = $user->guruStaf->strukturJabatan
                ->map(fn($sj) => $sj->jabatan?->nama_jabatan)
                ->filter()
                ->contains(function ($value) use ($allowedJabatans) {
                    return in_array($value, $allowedJabatans);
                });
        }

        return $hasRole || $hasJabatan;
    }
}