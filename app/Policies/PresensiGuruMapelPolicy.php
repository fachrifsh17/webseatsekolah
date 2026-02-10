<?php

namespace App\Policies;

use App\Models\PresensiGuruMapel;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PresensiGuruMapelPolicy
{
    use HandlesAuthorization;

    /**
     * Jalankan sebelum pengecekan lainnya.
     * Memberikan akses penuh kepada Admin secara otomatis.
     */
    public function before(User $user, $ability)
    {
        if ($this->isAdmin($user)) {
            return true;
        }
    }

    private function getGuruId(User $user)
    {
        return $user->guru?->id ?? $user->guruStaf?->id;
    }

    private function isAdmin(User $user): bool
    {
        // Pengecekan multi-case untuk fleksibilitas role name
        return method_exists($user, 'hasRole') && 
               ($user->hasRole('Admin') || $user->hasRole('admin') || $user->hasRole('ADMIN'));
    }

    private function isManagement(User $user): bool
    {
        $jabatanUser = $user->guruStaf?->strukturJabatan ?? collect();
        return $jabatanUser->contains(function ($sj) {
            $nama = strtolower($sj->jabatan?->nama_jabatan ?? '');
            return in_array($nama, ['waka kesiswaan', 'kesiswaan', 'kepala sekolah']);
        });
    }

    public function viewAny(User $user): bool
    {
        // Semua user terautentikasi bisa melihat daftar (index)
        return true;
    }

    public function view(User $user, PresensiGuruMapel $presensi): bool
    {
        // Management (Kepsek/Waka) bisa melihat detail semua presensi
        if ($this->isManagement($user)) {
            return true;
        }

        // Guru hanya bisa melihat presensi yang dia buat sendiri
        $guruId = $this->getGuruId($user);
        return $presensi->guruMapel?->guru_staf_id === $guruId;
    }

    public function create(User $user): bool
    {
        // Management tidak boleh input presensi (hanya Guru & Admin)
        if ($this->isManagement($user)) {
            return false;
        }

        // Izinkan jika user terhubung ke data Guru/Staf
        return !is_null($this->getGuruId($user));
    }

    public function update(User $user, PresensiGuruMapel $presensi): bool
    {
        // Management tidak boleh edit
        if ($this->isManagement($user)) {
            return false; 
        }

        // Guru hanya boleh edit miliknya sendiri
        $guruId = $this->getGuruId($user);
        return $presensi->guruMapel?->guru_staf_id === $guruId;
    }

    public function delete(User $user, PresensiGuruMapel $presensi): bool
    {
        // Secara eksplisit mengembalikan false untuk non-admin
        // Admin tetap bisa menghapus karena sudah ditangani oleh fungsi before()
        return false;
    }
}