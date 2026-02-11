<?php

namespace App\Policies;

use App\Models\User;
use App\Models\GuruMapel;

class GuruMapelPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['waka-kurikulum', 'ketua-jurusan']);
    }

    public function view(User $user, GuruMapel $guruMapel): bool
    {
        return $this->authorize($user, ['Admin'], ['waka-kurikulum', 'ketua-jurusan']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['waka-kurikulum']);
    }

    public function update(User $user, GuruMapel $guruMapel): bool
    {
        return $this->authorize($user, ['Admin'], ['waka-kurikulum']);
    }

    public function delete(User $user, GuruMapel $guruMapel): bool
    {
        return $this->authorize($user, ['Admin'], ['waka-kurikulum']);
    }

    public function export(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['waka-kurikulum', 'ketua-jurusan']);
    }

    public function import(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['waka-kurikulum']);
    }

    protected function authorize(User $user, array $allowedRoles = [], array $allowedJabatans = []): bool
    {
        $hasRole = $user->roles->pluck('role_name')->intersect($allowedRoles)->isNotEmpty();

        $jabatanSlugs = $user->guruStaf
            ? $user->guruStaf->strukturJabatan
                ->map(fn($sj) => $sj->jabatan?->slug)
                ->filter()
                ->toArray()
            : [];

        $hasJabatan = !empty(array_intersect($jabatanSlugs, $allowedJabatans));

        return $hasRole || $hasJabatan;
    }
}
