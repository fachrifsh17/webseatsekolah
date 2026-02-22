<?php

namespace App\Policies;

use App\Models\Fasilitas;
use App\Models\User;

class FasilitasPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Sarpras']);
    }

    public function view(User $user, Fasilitas $fasilitas): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Sarpras']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Sarpras']);
    }

    public function update(User $user, Fasilitas $fasilitas): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Sarpras']);
    }

    public function delete(User $user, Fasilitas $fasilitas): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Sarpras']);
    }

    public function restore(User $user, Fasilitas $fasilitas): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Sarpras']);
    }

    public function forceDelete(User $user, Fasilitas $fasilitas): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Sarpras']);
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

        // 2. Cek Jabatan melalui relasi guruStaf -> strukturJabatan
        if ($user->guruStaf && $user->guruStaf->strukturJabatan) {
            return $user->guruStaf->strukturJabatan->contains(function ($sj) use ($allowedJabatans) {
                return $sj->jabatan && in_array($sj->jabatan->nama_jabatan, $allowedJabatans);
            });
        }

        return false;
    }
}