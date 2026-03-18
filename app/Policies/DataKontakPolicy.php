<?php

namespace App\Policies;

use App\Models\User;

class DataKontakPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function view(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function update(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function delete(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function restore(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function forceDelete(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    /**
     * Menggunakan contains agar lebih stabil membandingkan string role
     */
    protected function authorize(User $user, array $allowedRoles = []): bool
    {
        return $user->roles->pluck('role_name')->contains(function ($roleName) use ($allowedRoles) {
            return in_array($roleName, $allowedRoles);
        });
    }
}