<?php

namespace App\Policies;

use App\Models\Kelas;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class KelasPolicy
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
        return false;
    }

    public function view(User $user, Kelas $kelas): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Kelas $kelas): bool
    {
        return false;
    }

    public function delete(User $user, Kelas $kelas): bool
    {
        return false;
    }

    public function restore(User $user, Kelas $kelas): bool
    {
        return false;
    }

    public function forceDelete(User $user, Kelas $kelas): bool
    {
        return false;
    }
}