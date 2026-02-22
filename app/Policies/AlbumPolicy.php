<?php

namespace App\Policies;

use App\Models\Album;
use App\Models\User;

class AlbumPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Sarpras']);
    }

    public function view(User $user, Album $album): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Sarpras']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Sarpras']);
    }

    public function update(User $user, Album $album): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Sarpras']);
    }

    public function delete(User $user, Album $album): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Sarpras']);
    }

    // --- Fungsi Authorize yang Baru (Tanpa Intersect) ---
    protected function authorize(User $user, array $allowedRoles = [], array $allowedJabatans = []): bool
    {
        // 1. Cek Role (Cukup pakai contains pada koleksi roles)
        $hasRole = $user->roles->contains(function ($role) use ($allowedRoles) {
            return in_array($role->role_name, $allowedRoles);
        });

        if ($hasRole) return true;

        // 2. Cek Jabatan (Melalui relasi guruStaf -> strukturJabatan)
        $hasJabatan = false;
        if ($user->guruStaf && $user->guruStaf->strukturJabatan) {
            $hasJabatan = $user->guruStaf->strukturJabatan->contains(function ($sj) use ($allowedJabatans) {
                return $sj->jabatan && in_array($sj->jabatan->nama_jabatan, $allowedJabatans);
            });
        }

        return $hasJabatan;
    }
}