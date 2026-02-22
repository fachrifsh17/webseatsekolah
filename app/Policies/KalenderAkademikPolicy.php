<?php

namespace App\Policies;

use App\Models\KalenderAkademik;
use App\Models\User;

class KalenderAkademikPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin', 'Guru', 'Siswa', 'Orangtua'], ['Waka Kurikulum']);
    }

    public function view(User $user, KalenderAkademik $kalenderAkademik): bool
    {
        return $this->authorize($user, ['Admin', 'Guru', 'Siswa', 'Orangtua'], ['Waka Kurikulum']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    public function update(User $user, KalenderAkademik $kalenderAkademik): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    public function delete(User $user, KalenderAkademik $kalenderAkademik): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    /**
     * Helper untuk validasi Role dan Jabatan tanpa Intersect yang berat
     */
    protected function authorize(User $user, array $allowedRoles = [], array $allowedJabatans = []): bool
    {
        // 1. Cek Role (menggunakan contains lebih aman daripada intersect untuk debugging)
        $hasRole = $user->roles->contains(fn($role) => in_array($role->role_name, $allowedRoles));

        if ($hasRole) return true;

        // 2. Cek Jabatan melalui relasi guruStaf
        if ($user->guruStaf && $user->guruStaf->strukturJabatan) {
            return $user->guruStaf->strukturJabatan->contains(function ($sj) use ($allowedJabatans) {
                return $sj->jabatan && in_array($sj->jabatan->nama_jabatan, $allowedJabatans);
            });
        }

        return false;
    }
}