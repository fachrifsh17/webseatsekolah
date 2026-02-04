<?php

namespace App\Policies;

use App\Models\Jurusan;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class JurusanPolicy
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

    public function view(User $user, Jurusan $jurusan): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Jurusan $jurusan): bool
    {
        return false;
    }

    public function delete(User $user, Jurusan $jurusan): bool
    {
        return false;
    }

    public function restore(User $user, Jurusan $jurusan): bool
    {
        return false;
    }

    public function forceDelete(User $user, Jurusan $jurusan): bool
    {
        return false;
    }
}