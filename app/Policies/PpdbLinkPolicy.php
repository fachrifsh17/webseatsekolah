<?php

namespace App\Policies;

use App\Models\PpdbLink;
use App\Models\User;

class PpdbLinkPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function view(User $user, ?PpdbLink $ppdbLink = null): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ?PpdbLink $ppdbLink = null): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function delete(User $user, ?PpdbLink $ppdbLink = null): bool
    {
        return false;
    }

    public function restore(User $user, ?PpdbLink $ppdbLink = null): bool
    {
        return false;
    }

    public function forceDelete(User $user, ?PpdbLink $ppdbLink = null): bool
    {
        return false;
    }

    /**
     * Menggunakan contains untuk otorisasi berbasis nama role dan nama jabatan
     */
    protected function authorize(User $user, array $allowedRoles = [], array $allowedJabatans = []): bool
    {
        // 1. Cek Role (Admin)
        $hasRole = $user->roles->pluck('role_name')->contains(function ($role) use ($allowedRoles) {
            return in_array($role, $allowedRoles);
        });

        // 2. Cek Jabatan (Waka Humas)
        $hasJabatan = false;
        if ($user->guruStaf && $user->guruStaf->strukturJabatan) {
            $hasJabatan = $user->guruStaf->strukturJabatan
                ->map(fn($sj) => $sj->jabatan?->nama_jabatan)
                ->filter()
                ->contains(function ($namaJabatan) use ($allowedJabatans) {
                    return in_array($namaJabatan, $allowedJabatans);
                });
        }

        return $hasRole || $hasJabatan;
    }
}