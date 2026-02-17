<?php

namespace App\Policies;

use App\Models\PoinSiswa;
use App\Models\User;

class PoinSiswaPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin', 'Guru', 'Siswa', 'Orangtua'], ['Waka Kesiswaan']);
    }

    public function view(User $user, PoinSiswa $poinSiswa): bool
    {
        return $this->authorize($user, ['Admin', 'Guru', 'Siswa', 'Orangtua'], ['Waka Kesiswaan']);
    }

    public function create(User $user): bool
    {
        return $this->authorizeRoute($user, ['Admin']) || $this->authorize($user, ['Guru'], ['Waka Kesiswaan']);
    }

    public function update(User $user, PoinSiswa $poinSiswa): bool
    {
        // Izinkan jika Admin ATAU jika dia Guru yang menginput poin tersebut
        if ($this->authorizeRoute($user, ['Admin'])) return true;
        
        return $user->guru_staf_id === $poinSiswa->guru_staf_id || $this->authorize($user, [], ['Waka Kesiswaan']);
    }

    public function delete(User $user, PoinSiswa $poinSiswa): bool
    {
        if ($this->authorizeRoute($user, ['Admin'])) return true;

        return $user->guru_staf_id === $poinSiswa->guru_staf_id || $this->authorize($user, [], ['Waka Kesiswaan']);
    }

    public function export(User $user): bool
    {
        return $this->authorizeRoute($user, ['Admin']) || $this->authorize($user, ['Waka Kesiswaan']);
    }

    protected function authorize(User $user, array $allowedRoles = [], array $allowedJabatans = []): bool
    {
        // Menggunakan contains: mengecek apakah ada salah satu role yang diizinkan
        $hasRole = $user->roles->pluck('role_name')->contains(fn($role) => in_array($role, $allowedRoles));

        $hasJabatan = false;
        if ($user->guruStaf) {
            $jabatans = $user->guruStaf->strukturJabatan->map(fn($sj) => $sj->jabatan?->nama_jabatan)->filter();
            $hasJabatan = $jabatans->contains(fn($jabatan) => in_array($jabatan, $allowedJabatans));
        }

        return $hasRole || $hasJabatan;
    }

    protected function authorizeRoute(User $user, array $allowedRoles = []): bool
    {
        $hasRole = $user->roles->pluck('role_name')->contains(fn($role) => in_array($role, $allowedRoles));

        return $hasRole && request()->is('api/admin/*');
    }
}