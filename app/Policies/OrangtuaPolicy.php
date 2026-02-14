<?php

namespace App\Policies;

use App\Models\Orangtua;
use App\Models\User;

class OrangtuaPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin', 'Guru'], ['Waka Kesiswaan', 'Wali Kelas']);
    }

    public function view(User $user, Orangtua $orangtua): bool
    {
        if ($this->authorize($user, ['Admin'], ['Waka Kesiswaan'])) {
            return true;
        }

        if ($this->authorize($user, [], ['Wali Kelas'])) {
            $idKelasWali = $user->guruStaf?->kelas_id;
            return $orangtua->anak()->where('kelas_id', $idKelasWali)->exists();
        }

        // Menggunakan array untuk mengecek role Orangtua
        $userRoles = $user->roles->pluck('role_name')->toArray();
        if (in_array('Orangtua', $userRoles)) {
            return $user->orangtua_id === $orangtua->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $this->authorizeRoute($user, ['Admin']);
    }

    public function update(User $user, Orangtua $orangtua): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kesiswaan']);
    }

    public function delete(User $user, Orangtua $orangtua): bool
    {
        return $this->authorizeRoute($user, ['Admin']);
    }

    public function restore(User $user, Orangtua $orangtua): bool
    {
        return $this->authorizeRoute($user, ['Admin']);
    }

    public function forceDelete(User $user, Orangtua $orangtua): bool
    {
        return $this->authorizeRoute($user, ['Admin']);
    }

    public function export(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kesiswaan', 'Wali Kelas']);
    }

    public function import(User $user): bool
    {
        return $this->authorizeRoute($user, ['Admin']);
    }

    protected function authorize(User $user, array $allowedRoles = [], array $allowedJabatans = []): bool
    {
        // Ubah collection menjadi array murni untuk menghindari getKey()
        $userRoles = $user->roles->pluck('role_name')->toArray();
        $hasRole = count(array_intersect($userRoles, $allowedRoles)) > 0;

        $hasJabatan = false;
        if ($user->guruStaf) {
            $userJabatans = $user->guruStaf->strukturJabatan
                ->map(fn($sj) => $sj->jabatan?->nama_jabatan)
                ->filter()
                ->toArray();
            
            $hasJabatan = count(array_intersect($userJabatans, $allowedJabatans)) > 0;
        }

        return $hasRole || $hasJabatan;
    }

    protected function authorizeRoute(User $user, array $allowedRoles = []): bool
    {
        // Ubah ke array murni
        $userRoles = $user->roles->pluck('role_name')->toArray();
        $isAdmin = count(array_intersect($userRoles, $allowedRoles)) > 0;

        if ($isAdmin && request()->is('api/admin/*')) {
            return true;
        }

        return false;
    }
}