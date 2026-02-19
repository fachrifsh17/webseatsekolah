<?php

namespace App\Policies;

use App\Models\Presensi;
use App\Models\User;

class PresensiPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Guru', 'Siswa', 'Orangtua'], ['Waka Kesiswaan', 'Kepala Sekolah']) 
            || $this->authorizeRoute($user, ['Admin']);
    }

    public function view(User $user, Presensi $presensi): bool
    {
        return $this->authorize($user, ['Guru', 'Siswa', 'Orangtua'], ['Waka Kesiswaan', 'Kepala Sekolah']) 
            || $this->authorizeRoute($user, ['Admin']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Guru'], ['Waka Kesiswaan']) 
            || $this->authorizeRoute($user, ['Admin']);
    }

    public function update(User $user, Presensi $presensi): bool
    {
        return $this->authorize($user, ['Guru'], ['Waka Kesiswaan']) 
            || $this->authorizeRoute($user, ['Admin']);
    }

    public function delete(User $user, Presensi $presensi): bool
    {
        return $this->authorize($user, [], ['Waka Kesiswaan']) 
            || $this->authorizeRoute($user, ['Admin']);
    }

    public function export(User $user): bool
    {
        return $this->authorize($user, ['Guru'], ['Waka Kesiswaan', 'Kepala Sekolah']) 
            || $this->authorizeRoute($user, ['Admin']);
    }

    protected function authorize(User $user, array $allowedRoles = [], array $allowedJabatans = []): bool
    {
        // Menggunakan contains untuk mengecek role
        $hasRole = $user->roles->pluck('role_name')->contains(function ($value) use ($allowedRoles) {
            return in_array($value, $allowedRoles);
        });

        if ($hasRole) return true;

        // Cek Jabatan menggunakan contains pada relasi guruStaf
        $hasJabatan = false;
        if ($user->guruStaf) {
            $hasJabatan = $user->guruStaf->strukturJabatan
                ->map(fn($sj) => $sj->jabatan?->nama_jabatan)
                ->filter()
                ->contains(function ($value) use ($allowedJabatans) {
                    return in_array($value, $allowedJabatans);
                });
        }

        return $hasJabatan;
    }

    protected function authorizeRoute(User $user, array $allowedRoles = []): bool
    {
        // Menggunakan contains untuk mengecek Admin
        $isAdmin = $user->roles->pluck('role_name')->contains(function ($value) use ($allowedRoles) {
            return in_array($value, $allowedRoles);
        });

        if ($isAdmin && request()->is('api/admin/*')) {
            return true;
        }

        return false;
    }
}