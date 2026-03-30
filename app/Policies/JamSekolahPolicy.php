<?php

namespace App\Policies;

use App\Models\JamSekolah;
use App\Models\User;

class JamSekolahPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin', 'Guru', 'Siswa', 'Orangtua'], ['Waka Kurikulum']);
    }

    public function view(User $user, JamSekolah $jamSekolah): bool
    {
        return $this->authorize($user, ['Admin', 'Guru', 'Siswa', 'Orangtua'], ['Waka Kurikulum']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    public function update(User $user, JamSekolah $jamSekolah): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    public function delete(User $user, ?JamSekolah $jamSekolah = null): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    public function restore(User $user, JamSekolah $jamSekolah): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    public function forceDelete(User $user, JamSekolah $jamSekolah): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    public function export(User $user): bool
    {
        return $this->authorize($user, ['Admin', 'Guru', 'Siswa', 'Orangtua'], ['Waka Kurikulum']);
    }

    public function import(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kurikulum']);
    }

    protected function authorize(User $user, array $allowedRoles = [], array $allowedJabatans = []): bool
    {
        $hasRole = $user->roles->pluck('role_name')->contains(function ($value) use ($allowedRoles) {
            return in_array($value, $allowedRoles);
        });

        if ($hasRole) {
            return true;
        }

        if ($user->relationLoaded('guruStaf') && $user->guruStaf) {
            return $user->guruStaf->strukturJabatan
                ->map(fn($sj) => $sj->jabatan?->nama_jabatan)
                ->filter()
                ->contains(function ($value) use ($allowedJabatans) {
                    return in_array(trim($value), $allowedJabatans);
                });
        }

        return false;
    }
}