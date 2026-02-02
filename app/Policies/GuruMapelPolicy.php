<?php

namespace App\Policies;

use App\Models\User;
use App\Models\GuruMapel;

class GuruMapelPolicy
{
    public function view(User $user, GuruMapel $guruMapel): bool
    {
        return $this->checkAccess($user);
    }

    public function create(User $user): bool
    {
        return $this->checkAccess($user);
    }

    public function update(User $user, GuruMapel $guruMapel): bool
    {
        return $this->checkAccess($user);
    }

    public function delete(User $user, GuruMapel $guruMapel): bool
    {
        return $this->checkAccess($user);
    }

    protected function checkAccess(User $user): bool
    {
        if ($user->hasAnyRole(['admin', 'Admin', 'ADMIN'])) {
            return true;
        }

        $jabatan = optional($user->guruStaf->strukturJabatan)->slug;

        return in_array($jabatan, [
            'kepala-sekolah',
            'waka-kurikulum'
        ]);
    }
}
