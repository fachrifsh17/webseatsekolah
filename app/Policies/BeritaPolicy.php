<?php

namespace App\Policies;

use App\Models\Berita;
use App\Models\User;

class BeritaPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin', 'Guru', 'Siswa', 'Orangtua'], ['Waka Humas']);
    }

    public function view(User $user, Berita $berita): bool
    {
        return $this->authorize($user, ['Admin', 'Guru', 'Siswa', 'Orangtua'], ['Waka Humas']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function update(User $user, Berita $berita): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function delete(User $user, Berita $berita): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function restore(User $user, Berita $berita): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function forceDelete(User $user, Berita $berita): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    protected function authorize(User $user, array $allowedRoles = [], array $allowedJabatans = []): bool
    {
        // 1. Cek Role menggunakan contains
        $hasRole = $user->roles->contains(function ($role) use ($allowedRoles) {
            return in_array($role->role_name, $allowedRoles);
        });

        if ($hasRole) {
            return true;
        }

        // 2. Cek Jabatan (Pastikan relasi guruStaf dan strukturJabatan ada)
        if ($user->guruStaf && $user->guruStaf->strukturJabatan) {
            return $user->guruStaf->strukturJabatan->contains(function ($sj) use ($allowedJabatans) {
                // Pastikan relasi jabatan tidak null sebelum ambil nama_jabatan
                return $sj->jabatan && in_array($sj->jabatan->nama_jabatan, $allowedJabatans);
            });
        }

        return false;
    }
}