<?php

namespace App\Policies;

use App\Models\Ekstrakurikuler;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EkstrakurikulerPolicy
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
        return $user->jabatan === 'Waka Kesiswaan';
    }

    public function view(User $user, Ekstrakurikuler $ekstrakurikuler): bool
    {
        return $user->jabatan === 'Waka Kesiswaan';
    }

    public function create(User $user): bool
    {
        return $user->jabatan === 'Waka Kesiswaan';
    }

    public function update(User $user, Ekstrakurikuler $ekstrakurikuler): bool
    {
        return $user->jabatan === 'Waka Kesiswaan';
    }

    public function delete(User $user, Ekstrakurikuler $ekstrakurikuler): bool
    {
        return $user->jabatan === 'Waka Kesiswaan';
    }

    public function restore(User $user, Ekstrakurikuler $ekstrakurikuler): bool
    {
        return false;
    }

    public function forceDelete(User $user, Ekstrakurikuler $ekstrakurikuler): bool
    {
        return false;
    }
}