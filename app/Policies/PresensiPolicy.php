<?php

namespace App\Policies;

use App\Models\Presensi;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\DB;

class PresensiPolicy
{
    use HandlesAuthorization;

    public function before(User $user, $ability)
    {
        if ($user->hasRole('Admin')) return true;

        if ($user->guruStaf) {
            $isHighLevel = DB::table('struktur_jabatan')
                ->where('guru_staf_id', $user->guruStaf->id)
                ->whereIn('jabatan_id', [1, 3])
                ->exists();

            if ($isHighLevel && in_array($ability, ['viewAny', 'view'])) {
                return true;
            }
        }
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Presensi $presensi): bool
    {
        if ($user->hasRole('Guru') && $user->guruStaf) {
            return (string) $user->guruStaf->id === (string) $presensi->siswa?->kelas?->wali_kelas_id;
        }

        if ($user->hasRole('Siswa')) {
            return (string) $user->siswa_id === (string) $presensi->siswa_id;
        }

        if ($user->hasRole('Orang Tua')) {
            return DB::table('orangtua_siswa')
                ->where('orangtua_id', $user->orangtua_id)
                ->where('siswa_id', $presensi->siswa_id)
                ->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Guru');
    }

    public function update(User $user, Presensi $presensi): bool
    {
        return false;
    }

    public function delete(User $user, Presensi $presensi): bool
    {
        return false;
    }
}