<?php

namespace App\Policies;

use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TahunAjaranPolicy
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

    public function view(User $user, TahunAjaran $tahunAjaran): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, TahunAjaran $tahunAjaran): bool
    {
        return false;
    }

    public function delete(User $user, TahunAjaran $tahunAjaran): bool
    {
        return false;
    }

    public function restore(User $user, TahunAjaran $tahunAjaran): bool
    {
        return false;
    }

    public function forceDelete(User $user, TahunAjaran $tahunAjaran): bool
    {
        return false;
    }
}