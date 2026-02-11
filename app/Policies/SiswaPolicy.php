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

        if ($user->roles->pluck('role_name')->contains('Siswa')) {
            return $user->siswa_id === $siswa->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function update(User $user, Siswa $siswa): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kesiswaan']);
    }

    public function delete(User $user, Siswa $siswa): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function restore(User $user, Siswa $siswa): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function forceDelete(User $user, Siswa $siswa): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function export(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Kesiswaan', 'Ketua Jurusan', 'Wali Kelas']);
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
