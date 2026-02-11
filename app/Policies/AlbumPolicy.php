<?php

namespace App\Policies;

use App\Models\Album;
use App\Models\User;

class AlbumPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Sarpras']);
    }

    public function view(User $user, Album $album): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Sarpras']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Sarpras']);
    }

    public function update(User $user, Album $album): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Sarpras']);
    }

    public function delete(User $user, Album $album): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Sarpras']);
    }

    public function restore(User $user, Album $album): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Sarpras']);
    }

    public function forceDelete(User $user, Album $album): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Sarpras']);
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
