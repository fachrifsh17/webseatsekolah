<?php

namespace App\Policies;

use App\Models\Semester;
use App\Models\User;

class SemesterPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function view(User $user, Semester $semester): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function update(User $user, Semester $semester): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function delete(User $user, Semester $semester): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function restore(User $user, Semester $semester): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function forceDelete(User $user, Semester $semester): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    protected function authorize(User $user, array $allowedRoles = []): bool
    {
        return $user->roles->pluck('role_name')->intersect($allowedRoles)->isNotEmpty();
    }
}