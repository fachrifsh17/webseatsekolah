<?php

namespace App\Policies;

use App\Models\GuruStaf;
use App\Models\User;

class GuruPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function view(User $user, GuruStaf $guruStaf): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function update(User $user, GuruStaf $guruStaf): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function delete(User $user, GuruStaf $guruStaf): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function restore(User $user, GuruStaf $guruStaf): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function forceDelete(User $user, GuruStaf $guruStaf): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function import(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function export(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    /**
     * Menggunakan contains untuk memvalidasi role secara aman
     */
    protected function authorize(User $user, array $allowedRoles = []): bool
    {
        return $user->roles->pluck('role_name')->contains(function ($roleName) use ($allowedRoles) {
            return in_array($roleName, $allowedRoles);
        });
    }
}