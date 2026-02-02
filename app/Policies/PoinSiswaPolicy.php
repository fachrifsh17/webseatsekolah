<?php

namespace App\Policies;

use App\Models\PoinSiswa;
use App\Models\User;
<<<<<<< HEAD
use Illuminate\Support\Facades\Request;

class PoinSiswaPolicy
{
    public function before(User $user, string $ability)
    {
        $isGuruScope = Request::query('scope') === 'guru';

        if ($user->hasAnyRole(['admin', 'Admin', 'ADMIN']) && !$isGuruScope) {
=======
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
>>>>>>> master
            return true;
        }
    }

    public function viewAny(User $user): bool
    {
<<<<<<< HEAD
        return $user->hasAnyRole(['admin', 'Admin', 'ADMIN', 'guru', 'Guru', 'siswa', 'Siswa', 'orangtua', 'Orangtua', 'Orang Tua']);
    }

    public function view(User $user, PoinSiswa $poin_siswa): bool
    {
        if ($user->hasAnyRole(['guru', 'Guru'])) {
            return (string) $user->guruStaf?->id === (string) $poin_siswa->guru_staf_id;
        }

        if ($user->hasAnyRole(['siswa', 'Siswa'])) {
            return (string) $user->siswa?->id === (string) $poin_siswa->siswa_id;
        }

        if ($user->hasAnyRole(['orangtua', 'Orangtua', 'Orang Tua'])) {
            return $user->orangtua?->anak()->where('siswa.id', $poin_siswa->siswa_id)->exists() ?? false;
=======
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
>>>>>>> master
        }

        return false;
    }

    public function create(User $user): bool
    {
<<<<<<< HEAD
        return $user->hasAnyRole(['admin', 'Admin', 'ADMIN', 'guru', 'Guru']);
    }

    public function update(User $user, PoinSiswa $poin_siswa): bool
    {
        if ($user->hasAnyRole(['admin', 'Admin', 'ADMIN'])) {
            return true;
        }

        if ($user->hasAnyRole(['guru', 'Guru'])) {
            return (string) $user->guruStaf?->id === (string) $poin_siswa->guru_staf_id;
=======
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
>>>>>>> master
        }

        return false;
    }

<<<<<<< HEAD
    public function delete(User $user, PoinSiswa $poin_siswa): bool
    {
        return $user->hasAnyRole(['admin', 'Admin', 'ADMIN']);
=======
    public function delete(User $user, PoinSiswa $poinSiswa): bool
    {
        $jabatan = $this->getJabatan($user);

        if ($jabatan === 'Waka Kesiswaan') {
            return true;
        }

        return false;
>>>>>>> master
    }
}