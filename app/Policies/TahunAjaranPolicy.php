<?php

namespace App\Policies;

use App\Models\TahunAjaran;
use App\Models\User;

class TahunAjaranPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function view(User $user, TahunAjaran $tahunAjaran): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function update(User $user, TahunAjaran $tahunAjaran): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function delete(User $user, TahunAjaran $tahunAjaran): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function restore(User $user, TahunAjaran $tahunAjaran): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function forceDelete(User $user, TahunAjaran $tahunAjaran): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    /**
     * Mengamankan otorisasi menggunakan contains
     */
    protected function authorize(User $user, array $allowedRoles = []): bool
    {
        return $user->roles->pluck('role_name')->contains(function ($roleName) use ($allowedRoles) {
            return in_array($roleName, $allowedRoles);
        });
    }
}