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
        if ($user->hasAnyRole(['Admin', 'admin', 'ADMIN'])) return true;
    }

    public function viewAny(User $user): bool
    {
        // Semua user yang login (Guru, Siswa, Ortu, Kesiswaan) bisa melihat index
        // Filter data dilakukan di query Controller
        return true;
    }

    public function view(User $user, Presensi $presensi): bool
    {
        // 1. Cek Jabatan (Kepsek/Kesiswaan): Bisa melihat semua data secara global
        if ($user->guruStaf) {
            $isHighLevel = DB::table('struktur_jabatan')
                ->where('guru_staf_id', $user->guruStaf->id)
                ->whereIn('jabatan_id', [1, 3]) // ID 1 (Kepsek), ID 3 (Waka Kesiswaan)
                ->exists();

            if ($isHighLevel) return true;
        }

        // 2. Wali Kelas: Hanya bisa melihat presensi siswa di kelasnya
        if ($user->hasRole('Guru') && $user->guruStaf) {
            return (string) $user->guruStaf->id === (string) $presensi->siswa?->kelas?->wali_kelas_id;
        }

        // 3. Siswa: Hanya bisa melihat presensi miliknya sendiri
        if ($user->hasRole('Siswa')) {
            return (string) $user->siswa_id === (string) $presensi->siswa_id;
        }

        // 4. Orang Tua: Hanya bisa melihat presensi anaknya
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
        // Kesiswaan & Kepsek TIDAK BISA input (create)
        if ($user->guruStaf) {
            $isHighLevel = DB::table('struktur_jabatan')
                ->where('guru_staf_id', $user->guruStaf->id)
                ->whereIn('jabatan_id', [1, 3])
                ->exists();
                
            if ($isHighLevel) return false;
        }

        // Hanya Guru yang bisa menginput presensi
        return $user->hasRole('Guru');
    }

    public function update(User $user, Presensi $presensi): bool
    {
        // Guru (Wali Kelas) bisa update data presensi di kelasnya sendiri
        if ($user->hasRole('Guru') && $user->guruStaf) {
            return (string) $user->guruStaf->id === (string) $presensi->siswa?->kelas?->wali_kelas_id;
        }

        return false;
    }

    public function delete(User $user, Presensi $presensi): bool
    {
        // Kesiswaan, Kepsek, dan Guru tidak bisa hapus (Hanya Admin via before)
        return false;
    }
}