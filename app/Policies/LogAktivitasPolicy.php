<?php

namespace App\Policies;

use App\Models\User;

class LogAktivitasPolicy
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
        // Log aktivitas biasanya tidak boleh dibuat manual via API
        return false;
    }

    public function update(User $user): bool
    {
        // Log aktivitas tidak boleh diubah
        return false;
    }

    public function delete(User $user): bool
    {
        // Log aktivitas tidak boleh dihapus
        return false;
    }

    public function restore(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user): bool
    {
        return false;
    }
    protected function authorize(User $user, array $allowedRoles = [], array $allowedJabatans = []): bool
    {
        $hasRole = $user->roles->pluck('role_name')->contains(function ($role) use ($allowedRoles) {
            return in_array($role, $allowedRoles);
        });

        
        $hasJabatan = $user->guruStaf
            ? $user->guruStaf->strukturJabatan
                ->map(fn($sj) => $sj->jabatan?->nama_jabatan)
                ->filter()
                ->contains(function ($jabatan) use ($allowedJabatans) {
                    return in_array($jabatan, $allowedJabatans);
                })
            : false;

        return $hasRole || $hasJabatan;
    }
}