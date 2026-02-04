<?php

namespace App\Policies;

use App\Models\JamSekolah;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class JamSekolahPolicy
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
        return $user->jabatan === 'Waka Kurikulum' || $user->hasRole('siswa');
    }

    public function view(User $user, JamSekolah $jamSekolah): bool
    {
        return $user->jabatan === 'Waka Kurikulum' || $user->hasRole('siswa');
    }

    public function create(User $user): bool
    {
        return $user->jabatan === 'Waka Kurikulum';
    }

    public function update(User $user, JamSekolah $jamSekolah): bool
    {
        return $user->jabatan === 'Waka Kurikulum';
    }

    public function delete(User $user, JamSekolah $jamSekolah): bool
    {
        return $user->jabatan === 'Waka Kurikulum';
    }

    public function restore(User $user, JamSekolah $jamSekolah): bool
    {
        return false;
    }

    public function forceDelete(User $user, JamSekolah $jamSekolah): bool
    {
        return false;
    }
}