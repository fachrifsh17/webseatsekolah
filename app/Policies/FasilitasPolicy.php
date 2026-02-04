<?php

namespace App\Policies;

use App\Models\Fasilitas;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FasilitasPolicy
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
        return $user->jabatan === 'Waka Sarpras';
    }

    public function view(User $user, Fasilitas $fasilitas): bool
    {
        return $user->jabatan === 'Waka Sarpras';
    }

    public function create(User $user): bool
    {
        return $user->jabatan === 'Waka Sarpras';
    }

    public function update(User $user, Fasilitas $fasilitas): bool
    {
        return $user->jabatan === 'Waka Sarpras';
    }

    public function delete(User $user, Fasilitas $fasilitas): bool
    {
        return $user->jabatan === 'Waka Sarpras';
    }

    public function restore(User $user, Fasilitas $fasilitas): bool
    {
        return false;
    }

    public function forceDelete(User $user, Fasilitas $fasilitas): bool
    {
        return false;
    }
}