<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function view(User $user, Role $role): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function update(User $user, Role $role): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function restore(User $user, Role $role): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function forceDelete(User $user, Role $role): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    protected function authorize(User $user, array $allowedRoles = []): bool
    {
        return $user->roles->pluck('role_name')->intersect($allowedRoles)->isNotEmpty();
    }
}
