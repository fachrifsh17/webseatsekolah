<?php

namespace App\Policies;

use App\Models\Orangtua;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrangtuaPolicy
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
        return in_array($user->jabatan, ['Waka Kesiswaan', 'Wali Kelas']) || $user->hasRole('guru');
    }

    public function view(User $user, Orangtua $orangtua): bool
    {
        if ($user->jabatan === 'Waka Kesiswaan') {
            return true;
        }

        if ($user->jabatan === 'Wali Kelas') {
            $idKelasWali = $user->guruStaf?->kelas_id;
            return $orangtua->anak()->where('kelas_id', $idKelasWali)->exists();
        }

        if ($user->hasRole('orangtua')) {
            return $user->orangtua_id === $orangtua->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Orangtua $orangtua): bool
    {
        return $user->jabatan === 'Waka Kesiswaan';
    }

    public function delete(User $user, Orangtua $orangtua): bool
    {
        return false;
    }

    public function restore(User $user, Orangtua $orangtua): bool
    {
        return false;
    }

    public function forceDelete(User $user, Orangtua $orangtua): bool
    {
        return false;
    }
}