<?php

namespace App\Policies;

use App\Models\Prestasi;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PrestasiPolicy
{
    use HandlesAuthorization;

    public function before(User $user, $capability)
    {
        if ($user->hasRole('Admin')) {
            return true;
        }
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('guru');
    }

    public function view(User $user, Prestasi $prestasi): bool
    {
        return $user->hasRole('guru');
    }

    public function create(User $user): bool
    {
        return $user->jabatan === 'Waka Humas';
    }

    public function update(User $user, Prestasi $prestasi): bool
    {
        return $user->jabatan === 'Waka Humas';
    }

    public function delete(User $user, Prestasi $prestasi): bool
    {
        return $user->jabatan === 'Waka Humas';
    }

    public function restore(User $user, Prestasi $prestasi): bool
    {
        return false;
    }

    public function forceDelete(User $user, Prestasi $prestasi): bool
    {
        return false;
    }
}