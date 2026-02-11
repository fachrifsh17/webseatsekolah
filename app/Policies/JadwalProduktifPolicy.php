<?php

namespace App\Policies;

use App\Models\JadwalProduktif;
use App\Models\User;

class JadwalProduktifPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin', 'Siswa'], ['Kepala Sekolah', 'Waka Kurikulum']);
    }

    public function view(User $user, JadwalProduktif $jadwalProduktif): bool
    {
        return $this->authorize($user, ['Admin', 'Siswa'], ['Kepala Sekolah', 'Waka Kurikulum']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Kepala Sekolah', 'Waka Kurikulum']);
    }

    public function update(User $user, JadwalProduktif $jadwalProduktif): bool
    {
        return $this->authorize($user, ['Admin'], ['Kepala Sekolah', 'Waka Kurikulum']);
    }

    public function delete(User $user, JadwalProduktif $jadwalProduktif): bool
    {
        return $this->authorize($user, ['Admin'], ['Kepala Sekolah', 'Waka Kurikulum']);
    }

    public function restore(User $user, JadwalProduktif $jadwalProduktif): bool
    {
        return $this->authorize($user, ['Admin'], ['Kepala Sekolah', 'Waka Kurikulum']);
    }

    public function forceDelete(User $user, JadwalProduktif $jadwalProduktif): bool
    {
        return $this->authorize($user, ['Admin'], ['Kepala Sekolah', 'Waka Kurikulum']);
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
