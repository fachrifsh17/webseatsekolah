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
        // Admin selalu punya akses penuh
        if ($user->hasAnyRole(['admin', 'Admin', 'ADMIN'])) {
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

        // Kepsek dan Waka Kesiswaan bisa melihat semua poin
        if (in_array($jabatan, ['Kepala Sekolah', 'Waka Kesiswaan', 'Kesiswaan'])) {
            return true;
        }

        // Guru hanya bisa melihat poin yang mereka input sendiri
        if ($user->guruStaf && (int) $user->guruStaf->id === (int) $poinSiswa->guru_staf_id) {
            return true;
        }

        // Siswa hanya bisa melihat poin miliknya sendiri
        if ($user->siswa && (int) $user->siswa->id === (int) $poinSiswa->siswa_id) {
            return true;
        }

        // Orang tua hanya bisa melihat poin anaknya
        if ($user->orangtua) {
            return $user->orangtua->anak()->where('siswa.id', $poinSiswa->siswa_id)->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        $jabatan = $this->getJabatan($user);

        // Kepsek tidak input poin, Siswa & Ortu juga tidak bisa
        if ($jabatan === 'Kepala Sekolah' || $user->hasAnyRole(['siswa', 'Siswa', 'orangtua', 'Orangtua'])) {
            return false;
        }

        // Semua Guru/Staf lainnya boleh input poin
        return $user->guruStaf !== null;
    }

    public function update(User $user, PoinSiswa $poinSiswa): bool
    {
        $jabatan = $this->getJabatan($user);

        // Waka Kesiswaan punya wewenang mengedit poin siapapun
        if ($jabatan === 'Waka Kesiswaan') {
            return true;
        }

        // Guru hanya bisa edit poin yang mereka buat sendiri
        if ($user->guruStaf && (int) $user->guruStaf->id === (int) $poinSiswa->guru_staf_id) {
            return true;
        }

        return false;
    }

    public function delete(User $user, PoinSiswa $poinSiswa): bool
    {
        $jabatan = $this->getJabatan($user);

        // Hanya Admin atau Waka Kesiswaan yang boleh menghapus poin
        if ($jabatan === 'Waka Kesiswaan' || $user->hasAnyRole(['admin', 'Admin', 'ADMIN'])) {
            return true;
        }

        return false;
    }
}