<?php

namespace App\Policies;

use App\Models\StrukturJabatan;
use App\Models\User;

class StrukturJabatanPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, StrukturJabatan $strukturJabatan): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function update(User $user, StrukturJabatan $strukturJabatan): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function delete(User $user, StrukturJabatan $strukturJabatan): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function restore(User $user, StrukturJabatan $strukturJabatan): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function forceDelete(User $user, StrukturJabatan $strukturJabatan): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    protected function authorize(User $user, array $allowedRoles = []): bool
    {
        return $user->roles->pluck('role_name')->contains(function ($roleName) use ($allowedRoles) {
            return in_array($roleName, $allowedRoles);
        });
    }
}