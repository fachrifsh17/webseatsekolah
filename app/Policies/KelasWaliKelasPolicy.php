<?php

namespace App\Policies;

use App\Models\KelasWaliKelas;
use App\Models\User;

class KelasWaliKelasPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function view(User $user, KelasWaliKelas $kelasWaliKelas): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function update(User $user, KelasWaliKelas $kelasWaliKelas): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function delete(User $user, KelasWaliKelas $kelasWaliKelas): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    // Tambahan untuk fitur khusus
    public function prepareNewYear(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function cloneToNewYear(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    public function bulkUpdateTingkat(User $user): bool
    {
        return $this->authorize($user, ['Admin']);
    }

    protected function authorize(User $user, array $allowedRoles = []): bool
    {
        $userRoles = $user->roles->pluck('role_name')->map(fn($r) => strtolower($r));
        return $userRoles->intersect(array_map('strtolower', $allowedRoles))->isNotEmpty();
    }
}