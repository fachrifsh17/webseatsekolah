<?php

namespace App\Policies;

use App\Models\PpdbLink;
use App\Models\User;

class PpdbLinkPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function view(User $user, PpdbLink $ppdbLink): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PpdbLink $ppdbLink): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    public function delete(User $user, PpdbLink $ppdbLink): bool
    {
        return false;
    }

    public function restore(User $user, PpdbLink $ppdbLink): bool
    {
        return false;
    }

    public function forceDelete(User $user, PpdbLink $ppdbLink): bool
    {
        return false;
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
