<?php

namespace App\Policies;

use App\Models\MataPelajaran; // Sesuaikan dengan nama model Anda
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MapelPolicy
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
        return in_array($user->jabatan, ['Waka Kurikulum', 'Ketua Jurusan']);
    }

    public function view(User $user, MataPelajaran $mataPelajaran): bool
    {
        return in_array($user->jabatan, ['Waka Kurikulum', 'Ketua Jurusan']);
    }

    public function create(User $user): bool
    {
        return $user->jabatan === 'Waka Kurikulum';
    }

    public function update(User $user, MataPelajaran $mataPelajaran): bool
    {
        return $user->jabatan === 'Waka Kurikulum';
    }

    public function delete(User $user, MataPelajaran $mataPelajaran): bool
    {
        return $user->jabatan === 'Waka Kurikulum';
    }

    public function restore(User $user, MataPelajaran $mataPelajaran): bool
    {
        return false;
    }

    public function forceDelete(User $user, MataPelajaran $mataPelajaran): bool
    {
        return false;
    }
}