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
        // Admin & Waka Kesiswaan bisa lihat semua
        if ($this->authorize($user, ['Admin'], ['Waka Kesiswaan'])) {
            return true;
        }

        // Wali Kelas hanya bisa lihat orangtua dari siswa di kelasnya
        if ($this->authorize($user, [], ['Wali Kelas'])) {
            $idKelasWali = $user->guruStaf?->kelas_id;
            return $orangtua->anak()->where('kelas_id', $idKelasWali)->exists();
        }

        // Orangtua hanya bisa lihat dirinya sendiri
        if ($user->hasRole('Orangtua')) {
            return $user->orangtua_id === $orangtua->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function update(User $user, Orangtua $orangtua): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kesiswaan']);
    }

    public function delete(User $user, Orangtua $orangtua): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function restore(User $user, Orangtua $orangtua): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function forceDelete(User $user, Orangtua $orangtua): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function export(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kesiswaan', 'Wali Kelas']);
    }

    public function import(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    protected function authorize(User $user, array $allowedRoles = [], array $allowedJabatans = []): bool
    {
        $hasRole = $user->roles->pluck('role_name')->intersect($allowedRoles)->isNotEmpty();

        $hasJabatan = $user->guruStaf
            ? $user->guruStaf->strukturJabatan
                ->map(fn($sj) => $sj->jabatan?->nama_jabatan)
                ->filter()
                ->intersect($allowedJabatans)
                ->isNotEmpty()
            : false;

        return $hasRole || $hasJabatan;
    }
}
