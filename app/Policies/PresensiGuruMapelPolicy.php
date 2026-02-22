<?php

namespace App\Policies;

use App\Models\PresensiGuruMapel;
use App\Models\User;
use Illuminate\Support\Facades\Request;

class PresensiGuruMapelPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Guru'], ['Waka Kesiswaan', 'Kepala Sekolah']) 
            || $this->authorizeRoute($user, ['Admin']);
    }

    public function view(User $user, PresensiGuruMapel $presensi): bool
    {
        return $this->authorize($user, ['Guru'], ['Waka Kesiswaan', 'Kepala Sekolah']) 
            || $this->authorizeRoute($user, ['Admin']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Guru'], ['Waka Kesiswaan']) 
            || $this->authorizeRoute($user, ['Admin']);
    }

    public function update(User $user, PresensiGuruMapel $presensi): bool
    {
        return $this->authorize($user, ['Guru'], ['Waka Kesiswaan']) 
            || $this->authorizeRoute($user, ['Admin']);
    }

    public function delete(User $user, PresensiGuruMapel $presensi): bool
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
        // Cek Role menggunakan contains
        $hasRole = $user->roles->contains(function ($role) use ($allowedRoles) {
            return in_array($role->role_name, $allowedRoles);
        });

        // Cek Jabatan menggunakan contains
        $hasJabatan = false;
        if ($user->guruStaf && $user->guruStaf->strukturJabatan) {
            $hasJabatan = $user->guruStaf->strukturJabatan->contains(function ($sj) use ($allowedJabatans) {
                return $sj->jabatan && in_array($sj->jabatan->nama_jabatan, $allowedJabatans);
            });
        }

        return $hasRole || $hasJabatan;
    }

    protected function authorizeRoute(User $user, array $allowedRoles = []): bool
    {
        // Cek Admin menggunakan contains
        $isAdmin = $user->roles->contains(function ($role) use ($allowedRoles) {
            return in_array($role->role_name, $allowedRoles);
        });

        if ($isAdmin && request()->is('api/admin/*')) {
            return true;
        }

        return false;
    }
}