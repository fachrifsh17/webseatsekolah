<?php

namespace App\Policies;

use App\Models\PortalSosmed;
use App\Models\User;

class PortalSosmedPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function view(User $user, PortalSosmed $portalSosmed): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function update(User $user, PortalSosmed $portalSosmed): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function delete(User $user, PortalSosmed $portalSosmed): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function restore(User $user, PortalSosmed $portalSosmed): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function forceDelete(User $user, PortalSosmed $portalSosmed): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    /**
     * Logika otorisasi kustom menggunakan contains.
     */
    protected function authorize(User $user, array $allowedRoles = [], array $allowedJabatans = []): bool
    {
        // 1. Cek apakah user memiliki salah satu Role yang diizinkan
        $userRoles = $user->roles->pluck('role_name'); // Mengambil semua nama role user
        $hasRole = collect($allowedRoles)->contains(function ($role) use ($userRoles) {
            return $userRoles->contains($role);
        });

        // 2. Cek apakah user memiliki salah satu Jabatan yang diizinkan (Hanya jika guruStaf ada)
        $hasJabatan = false;
        if ($user->guruStaf && $user->guruStaf->strukturJabatan) {
            $userJabatans = $user->guruStaf->strukturJabatan
                ->map(fn($sj) => $sj->jabatan?->nama_jabatan)
                ->filter();

            $hasJabatan = collect($allowedJabatans)->contains(function ($jabatan) use ($userJabatans) {
                return $userJabatans->contains($jabatan);
            });
        }

        // Return true jika salah satu kondisi terpenuhi
        return $hasRole || $hasJabatan;
    }
}