<?php

namespace App\Policies;

use App\Models\Pengumuman;
use App\Models\User;

class PengumumanPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin', 'Guru', 'Siswa', 'Orangtua'], ['Waka Humas']);
    }

    public function view(User $user, Pengumuman $pengumuman): bool
    {
        return $this->authorize($user, ['Admin', 'Guru', 'Siswa', 'Orangtua'], ['Waka Humas']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function update(User $user, Pengumuman $pengumuman): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function delete(User $user, Pengumuman $pengumuman): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function restore(User $user, Pengumuman $pengumuman): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function forceDelete(User $user, Pengumuman $pengumuman): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    protected function authorize(User $user, array $allowedRoles = [], array $allowedJabatans = []): bool
    {
        // Menggunakan contains untuk mengecek kecocokan role
        $hasRole = $user->roles->pluck('role_name')->contains(function ($value) use ($allowedRoles) {
            return in_array($value, $allowedRoles);
        });

        // Menggunakan contains untuk mengecek kecocokan jabatan jika user adalah Guru/Staf
        $hasJabatan = false;
        if ($user->guruStaf) {
            $hasJabatan = $user->guruStaf->strukturJabatan
                ->pluck('jabatan.nama_jabatan')
                ->filter()
                ->contains(function ($value) use ($allowedJabatans) {
                    return in_array($value, $allowedJabatans);
                });
        }

        return $hasRole || $hasJabatan;
    }
}