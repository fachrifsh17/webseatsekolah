<?php

namespace App\Policies;

use App\Models\Siswa;
use App\Models\User;

class SiswaPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kesiswaan', 'Ketua Jurusan', 'Wali Kelas']);
    }

    public function view(User $user, Siswa $siswa): bool
    {
        if ($this->authorize($user, ['Admin'], ['Waka Kesiswaan', 'Ketua Jurusan', 'Wali Kelas'])) {
            return true;
        }

        $roleNames = $user->roles->pluck('role_name')->toArray();

        if (in_array('Siswa', $roleNames)) {
            return $user->id === $siswa->user_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $this->authorizeRoute($user, ['Admin']);
    }

    public function update(User $user, Siswa $siswa): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kesiswaan']);
    }

    public function delete(User $user, Siswa $siswa): bool
    {
        return $this->authorizeRoute($user, ['Admin']);
    }

    public function restore(User $user, Siswa $siswa): bool
    {
        return $this->authorizeRoute($user, ['Admin']);
    }

    public function forceDelete(User $user, Siswa $siswa): bool
    {
        return $this->authorizeRoute($user, ['Admin']);
    }

    public function export(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kesiswaan', 'Ketua Jurusan', 'Wali Kelas']);
    }

    public function import(User $user): bool
    {
        return $this->authorizeRoute($user, ['Admin']);
    }

    protected function authorize(User $user, array $allowedRoles = [], array $allowedJabatans = []): bool
    {
        // Gunakan contains() untuk mengecek string di dalam collection, bukan intersect()
        $userRoles = $user->roles->pluck('role_name');
        $hasRole = $userRoles->ensure('string')->some(fn($role) => in_array($role, $allowedRoles));

        $hasJabatan = false;
        if ($user->guruStaf) {
            $userJabatans = $user->guruStaf->strukturJabatan
                ->map(fn($sj) => $sj->jabatan?->nama_jabatan)
                ->filter();
            
            $hasJabatan = $userJabatans->some(fn($jabatan) => in_array($jabatan, $allowedJabatans));
        }

        return $hasRole || $hasJabatan;
    }

    protected function authorizeRoute(User $user, array $allowedRoles = []): bool
    {
        $userRoles = $user->roles->pluck('role_name');
        $hasRole = $userRoles->some(fn($role) => in_array($role, $allowedRoles));

        if ($hasRole && request()->is('api/admin/*')) {
            return true;
        }

        return false;
    }
}