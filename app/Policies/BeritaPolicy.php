<?php

namespace App\Policies;

use App\Models\Berita;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BeritaPolicy
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
        return $user->hasRole('guru') || $user->jabatan === 'Kepala Sekolah';
    }

    public function view(User $user, Berita $berita): bool
    {
        return $user->hasRole('guru') || $user->jabatan === 'Kepala Sekolah';
    }

    public function create(User $user): bool
    {
        return $user->jabatan === 'Waka Humas';
    }

    public function update(User $user, Berita $berita): bool
    {
        return $user->jabatan === 'Waka Humas';
    }

    public function delete(User $user, Berita $berita): bool
    {
        return $user->jabatan === 'Waka Humas';
    }

    public function restore(User $user, Berita $berita): bool
    {
        return false;
    }

    public function forceDelete(User $user, Berita $berita): bool
    {
        return false;
    }
}