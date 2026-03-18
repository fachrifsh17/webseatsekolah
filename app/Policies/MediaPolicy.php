<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Sarpras']);
    }

    public function view(User $user, Media $media): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Sarpras']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Sarpras']);
    }

    public function update(User $user, Media $media): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Sarpras']);
    }

    public function delete(User $user, Media $media): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Sarpras']);
    }

    public function deleteAny(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Sarpras']);
    }

    protected function authorize(User $user, array $allowedRoles = [], array $allowedJabatans = []): bool
    {
        $hasRole = $user->roles->contains(function ($role) use ($allowedRoles) {
            return in_array($role->role_name, $allowedRoles);
        });

        $hasJabatan = false;
        if ($user->guruStaf && $user->guruStaf->strukturJabatan) {
            $hasJabatan = $user->guruStaf->strukturJabatan->contains(function ($sj) use ($allowedJabatans) {
                return $sj->jabatan && in_array($sj->jabatan->nama_jabatan, $allowedJabatans);
            });
        }

        return $hasRole || $hasJabatan;
    }
}