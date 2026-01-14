<?php

namespace App\Policies;

use App\Models\PoinSiswa;
use App\Models\User;
use Illuminate\Support\Facades\Request;

class PoinSiswaPolicy
{
    public function before(User $user, string $ability)
    {
        $isGuruScope = Request::query('scope') === 'guru';

        if ($user->hasAnyRole(['admin', 'Admin', 'ADMIN']) && !$isGuruScope) {
            return true;
        }
    }

    public function viewAny(User $user): bool
    {
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
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'Admin', 'ADMIN', 'guru', 'Guru']);
    }

    public function update(User $user, PoinSiswa $poin_siswa): bool
    {
        if ($user->hasAnyRole(['admin', 'Admin', 'ADMIN'])) {
            return true;
        }

        if ($user->hasAnyRole(['guru', 'Guru'])) {
            return (string) $user->guruStaf?->id === (string) $poin_siswa->guru_staf_id;
        }

        return false;
    }

    public function delete(User $user, PoinSiswa $poin_siswa): bool
    {
        return $user->hasAnyRole(['admin', 'Admin', 'ADMIN']);
    }
}