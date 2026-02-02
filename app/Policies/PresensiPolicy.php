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
        // Admin memiliki akses penuh (Bypass)
        if ($user->hasRole('Admin')) return true;

        // Cek Jabatan Tinggi (Kepsek/Waka) untuk melihat data secara global
        if ($user->guruStaf) {
            $isHighLevel = DB::table('struktur_jabatan')
                ->where('guru_staf_id', $user->guruStaf->id)
                ->whereIn('jabatan_id', [1, 3]) // Sesuaikan ID jabatan Kepsek/Waka Anda
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
        // 1. Guru/Wali Kelas: Hanya bisa melihat presensi siswa di kelasnya
        if ($user->hasRole('Guru') && $user->guruStaf) {
            return (string) $user->guruStaf->id === (string) $presensi->siswa?->kelas?->wali_kelas_id;
        }

        // 2. Siswa: Hanya bisa melihat presensi miliknya sendiri
        if ($user->hasRole('Siswa')) {
            return (string) $user->siswa_id === (string) $presensi->siswa_id;
        }

        // 3. Orang Tua: Hanya bisa melihat presensi anaknya
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
        // Hanya Guru (Wali Kelas) yang bisa menginput presensi harian
        return $user->hasRole('Guru');
    }

    public function update(User $user, Presensi $presensi): bool
    {
        // Secara default false, kecuali diizinkan via before() untuk Admin
        return false;
    }

    public function delete(User $user, Presensi $presensi): bool
    {
        return false;
    }
}