<?php

namespace App\Policies;

use App\Models\DataKontak;
use App\Models\User;

class DataKontakPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function view(User $user, DataKontak $dataKontak): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function update(User $user, DataKontak $dataKontak): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function delete(User $user, DataKontak $dataKontak): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function restore(User $user, DataKontak $dataKontak): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function forceDelete(User $user, DataKontak $dataKontak): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    protected function authorize(User $user, array $allowedRoles = []): bool
    {
        return $user->roles->pluck('role_name')->intersect($allowedRoles)->isNotEmpty();
    }
}
