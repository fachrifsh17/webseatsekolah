<?php

namespace App\Policies;

use App\Models\ProfilSekolah;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProfilSekolahPolicy
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
        return $user->jabatan === 'Kepala Sekolah';
    }

    public function view(User $user, ProfilSekolah $profilSekolah): bool
    {
        return $user->jabatan === 'Kepala Sekolah';
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ProfilSekolah $profilSekolah): bool
    {
        return $user->jabatan === 'Kepala Sekolah';
    }

    public function delete(User $user, ProfilSekolah $profilSekolah): bool
    {
        return false;
    }

    public function restore(User $user, ProfilSekolah $profilSekolah): bool
    {
        return false;
    }

    public function forceDelete(User $user, ProfilSekolah $profilSekolah): bool
    {
        return false;
    }
}