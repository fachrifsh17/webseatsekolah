<?php

namespace App\Policies;

use App\Models\PoinSiswa;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PoinSiswaPolicy
{
    use HandlesAuthorization;

    private function getJabatan(User $user): string
    {
        return $user->guruStaf?->jabatan ?? '';
    }

    public function before(User $user, string $ability)
    {
        if ($user->hasRole('Admin') || $user->hasRole('admin')) {
            return true;
        }
    }

    public function viewAny(User $user): bool
    {
        return true; 
    }

    public function view(User $user, PoinSiswa $poinSiswa): bool
    {
        $jabatan = $this->getJabatan($user);

        if (in_array($jabatan, ['Kepala Sekolah', 'Waka Kesiswaan', 'Kesiswaan'])) {
            return true;
        }

        if ($user->guruStaf && (int) $user->guruStaf->id === (int) $poinSiswa->guru_staf_id) {
            return true;
        }

        if ($user->siswa_id && (int) $user->siswa_id === (int) $poinSiswa->siswa_id) {
            return true;
        }

        if ($user->orangtua_id) {
            return $user->orangtua->siswa->contains('id', $poinSiswa->siswa_id);
        }

        return false;
    }

    public function create(User $user): bool
    {
        $jabatan = $this->getJabatan($user);

        if ($jabatan === 'Kepala Sekolah') {
            return false;
        }

        if ($user->hasRole('siswa') || $user->hasRole('orangtua')) {
            return false;
        }

        return $user->guruStaf !== null;
    }

    public function update(User $user, PoinSiswa $poinSiswa): bool
    {
        $jabatan = $this->getJabatan($user);

        if ($jabatan === 'Waka Kesiswaan') {
            return true;
        }

        return false;
    }

    public function delete(User $user, PoinSiswa $poinSiswa): bool
    {
        $jabatan = $this->getJabatan($user);

        if ($jabatan === 'Waka Kesiswaan') {
            return true;
        }

        return false;
    }
}