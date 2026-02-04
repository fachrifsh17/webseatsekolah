<?php

namespace App\Policies;

use App\Models\JadwalProduktif;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class JadwalProduktifPolicy
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
        return in_array($user->jabatan, ['Waka Kurikulum', 'Ketua Jurusan']) || $user->hasRole('siswa');
    }

    public function view(User $user, JadwalProduktif $jadwalProduktif): bool
    {
        return in_array($user->jabatan, ['Waka Kurikulum', 'Ketua Jurusan']) || $user->hasRole('siswa');
    }

    public function create(User $user): bool
    {
        return in_array($user->jabatan, ['Waka Kurikulum', 'Ketua Jurusan']);
    }

    public function update(User $user, JadwalProduktif $jadwalProduktif): bool
    {
        return in_array($user->jabatan, ['Waka Kurikulum', 'Ketua Jurusan']);
    }

    public function delete(User $user, JadwalProduktif $jadwalProduktif): bool
    {
        return in_array($user->jabatan, ['Waka Kurikulum', 'Ketua Jurusan']);
    }

    public function restore(User $user, JadwalProduktif $jadwalProduktif): bool
    {
        return false;
    }

    public function forceDelete(User $user, JadwalProduktif $jadwalProduktif): bool
    {
        return false;
    }
}