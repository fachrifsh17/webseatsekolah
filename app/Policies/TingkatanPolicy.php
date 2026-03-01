<?php

namespace App\Policies;

use App\Models\Tingkatan;
use App\Models\User;

class TingkatanPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function view(User $user, Tingkatan $tingkatan): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function update(User $user, Tingkatan $tingkatan): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function delete(User $user, Tingkatan $tingkatan): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    protected function authorize(User $user, array $allowedRoles = []): bool
    {
        $userRoles = $user->roles->pluck('role_name')->map(fn($r) => strtolower($r));
        return $userRoles->intersect(array_map('strtolower', $allowedRoles))->isNotEmpty();
    }
}