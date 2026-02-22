<?php

namespace App\Policies;

use App\Models\Ekstrakurikuler;
use App\Models\User;

class EkstrakurikulerPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kesiswaan']);
    }

    public function view(User $user, Ekstrakurikuler $ekstrakurikuler): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kesiswaan']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kesiswaan']);
    }

    public function update(User $user, Ekstrakurikuler $ekstrakurikuler): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kesiswaan']);
    }

    public function delete(User $user, Ekstrakurikuler $ekstrakurikuler): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kesiswaan']);
    }

    public function restore(User $user, Ekstrakurikuler $ekstrakurikuler): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kesiswaan']);
    }

    public function forceDelete(User $user, Ekstrakurikuler $ekstrakurikuler): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kesiswaan']);
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

        // 2. Cek Jabatan (Pastikan relasi guruStaf dan strukturJabatan tersedia)
        if ($user->guruStaf && $user->guruStaf->strukturJabatan) {
            return $user->guruStaf->strukturJabatan->contains(function ($sj) use ($allowedJabatans) {
                // Pastikan relasi jabatan tidak null sebelum mengecek nama_jabatan
                return $sj->jabatan && in_array($sj->jabatan->nama_jabatan, $allowedJabatans);
            });
        }

        return false;
    }
}