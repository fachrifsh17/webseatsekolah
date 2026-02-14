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
        return $this->authorize($user, ['Admin'], ['waka-kurikulum', 'ketua-jurusan']);
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
        $userRoles = $user->roles->pluck('role_name')->map(fn($r) => strtolower($r));
        $hasRole = $userRoles->intersect(array_map('strtolower', $allowedRoles))->isNotEmpty();

        $jabatanSlugs = $user->guruStaf
            ? $user->guruStaf->strukturJabatan
                ->map(fn($sj) => strtolower($sj->jabatan?->slug))
                ->filter()
                ->toArray()
            : [];

        $hasJabatan = !empty(array_intersect($jabatanSlugs, array_map('strtolower', $allowedJabatans)));

        return $hasRole || $hasJabatan;
    }
}
