<?php

namespace App\Policies;

use App\Models\Siswa;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SiswaPolicy
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
        return in_array($user->jabatan, ['Waka Kesiswaan', 'Ketua Jurusan', 'Wali Kelas']);
    }

    public function view(User $user, Siswa $siswa): bool
    {
        if ($user->jabatan === 'Waka Kesiswaan') {
            return true;
        }

        if ($user->jabatan === 'Wali Kelas') {
            return $user->guruStaf?->kelas_id === $siswa->kelas_id;
        }

        if ($user->jabatan === 'Ketua Jurusan') {
            return $user->guruStaf?->jurusan_id === $siswa->jurusan_id;
        }

        if ($user->hasRole('siswa')) {
            return $user->siswa_id === $siswa->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Siswa $siswa): bool
    {
        return $user->jabatan === 'Waka Kesiswaan';
    }

    public function delete(User $user, Siswa $siswa): bool
    {
        return false;
    }

    public function restore(User $user, Siswa $siswa): bool
    {
        return false;
    }

    public function forceDelete(User $user, Siswa $siswa): bool
    {
        return false;
    }
}