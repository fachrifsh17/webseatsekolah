<?php

namespace App\Policies;

use App\Models\Pesan;
use App\Models\User;

class PesanPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function view(User $user, Pesan $pesan): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function update(User $user, Pesan $pesan): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function delete(User $user, Pesan $pesan): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function restore(User $user, Pesan $pesan): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function forceDelete(User $user, Pesan $pesan): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    protected function authorize(User $user, array $allowedRoles = [], array $allowedJabatans = []): bool
    {
        // Menggunakan contains untuk mengecek kecocokan role
        $hasRole = $user->roles->pluck('role_name')->contains(function ($roleName) use ($allowedRoles) {
            return in_array($roleName, $allowedRoles);
        });

        // Menggunakan contains untuk mengecek kecocokan jabatan
        $hasJabatan = false;
        if ($user->guruStaf) {
            $hasJabatan = $user->guruStaf->strukturJabatan
                ->pluck('jabatan.nama_jabatan')
                ->filter()
                ->contains(function ($jabatanName) use ($allowedJabatans) {
                    return in_array($jabatanName, $allowedJabatans);
                });
        }

        return $hasRole || $hasJabatan;
    }
}