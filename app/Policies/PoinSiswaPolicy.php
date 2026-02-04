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
        return strtolower(trim($user->guruStaf?->jabatan ?? ''));
    }

    public function before(User $user, string $ability)
    {
        // Admin (semua variasi penulisan) memiliki hak akses penuh ke semua fitur
        if ($user->hasAnyRole(['admin', 'Admin', 'ADMIN'])) {
            return true;
        }
    }

    public function viewAny(User $user): bool
    {
        // Semua user terautentikasi bisa melihat daftar poin (difilter di query controller)
        return true; 
    }

    public function view(User $user, PoinSiswa $poinSiswa): bool
    {
        $jabatan = $this->getJabatan($user);

        // Menyesuaikan Controller Kepsek & Kesiswaan: Boleh melihat data semua siswa
        if (in_array($jabatan, ['kepala sekolah', 'waka kesiswaan', 'kesiswaan'])) {
            return true;
        }

        // Guru: Hanya boleh lihat jika dia adalah penginputnya (guru_staf_id)
        if ($user->guru_staf_id && (int) $user->guru_staf_id === (int) $poinSiswa->guru_staf_id) {
            return true;
        }

        // Siswa: Hanya boleh lihat poin miliknya sendiri
        if ($user->siswa_id && (int) $user->siswa_id === (int) $poinSiswa->siswa_id) {
            return true;
        }

        // Orang Tua: Melihat poin berdasarkan relasi anak
        if ($user->orangtua) {
            return $user->orangtua->anak()->where('siswa.id', $poinSiswa->siswa_id)->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        $jabatan = $this->getJabatan($user);

        // Sesuai Controller Kesiswaan/Admin: Hanya Guru/Staf yang bisa input
        // Kepsek, Siswa, Ortu tidak diberikan akses input
        if (in_array($jabatan, ['kepala sekolah']) || $user->hasAnyRole(['siswa', 'orangtua'])) {
            return false;
        }

        return $user->guru_staf_id !== null;
    }

    public function update(User $user, PoinSiswa $poinSiswa): bool
    {
        $jabatan = $this->getJabatan($user);

        // Waka Kesiswaan atau Staf Kesiswaan bisa memperbaiki data poin siapapun
        if (in_array($jabatan, ['waka kesiswaan', 'kesiswaan'])) {
            return true;
        }

        // Guru: Hanya bisa update jika dia penginput poin tersebut
        return $user->guru_staf_id && (int) $user->guru_staf_id === (int) $poinSiswa->guru_staf_id;
    }

    public function delete(User $user, PoinSiswa $poinSiswa): bool
    {
        $jabatan = $this->getJabatan($user);

        // Sesuai standar keamanan di Controller Anda:
        // Hanya Waka Kesiswaan yang memiliki wewenang menghapus record
        return $jabatan === 'waka kesiswaan';
    }
}