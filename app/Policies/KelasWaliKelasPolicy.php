<?php

namespace App\Policies;

use App\Models\KelasWaliKelas;
use App\Models\User;

class KelasWaliKelasPolicy
{
    // Method Helper baru untuk mengecek admin langsung
    protected function isAdmin(User $user): bool
    {
        // Mengecek apakah user memiliki role 'Admin' (case-insensitive)
        return $user->roles->contains(function ($role) {
            return strtolower($role->role_name) === 'admin';
        });
    }

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, KelasWaliKelas $kelasWaliKelas): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, KelasWaliKelas $kelasWaliKelas): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, KelasWaliKelas $kelasWaliKelas): bool
    {
        return $this->isAdmin($user);
    }

    // Tambahan untuk fitur khusus
    public function prepareNewYear(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function cloneToNewYear(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function bulkUpdateTingkat(User $user): bool
    {
        return $this->isAdmin($user);
    }
}