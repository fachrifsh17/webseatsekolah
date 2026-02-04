<?php

namespace App\Policies;

use App\Models\PresensiGuruMapel;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PresensiGuruMapelPolicy
{
    use HandlesAuthorization;

    private function getGuruId(User $user)
    {
        return $user->guru?->id ?? $user->guruStaf?->id;
    }

    private function isAdmin(User $user): bool
    {
        return method_exists($user, 'hasRole') && ($user->hasRole('admin') || $user->hasRole('Admin') || $user->hasRole('ADMIN'));
    }

    private function isWakaKesiswaan(User $user): bool
    {
        $jabatanUser = $user->guruStaf?->strukturJabatan ?? collect();
        return $jabatanUser->contains(function ($sj) {
            $namaJabatan = strtolower($sj->jabatan?->nama_jabatan ?? '');
            return $namaJabatan === 'waka kesiswaan' || $namaJabatan === 'kesiswaan';
        });
    }

    private function isKepalaSekolah(User $user): bool
    {
        $jabatanUser = $user->guruStaf?->strukturJabatan ?? collect();
        return $jabatanUser->contains(function ($sj) {
            return strtolower($sj->jabatan?->nama_jabatan ?? '') === 'kepala sekolah';
        });
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PresensiGuruMapel $presensi): bool
    {
        // Admin, Kesiswaan, dan Kepsek bisa melihat semua data presensi
        if ($this->isAdmin($user) || $this->isWakaKesiswaan($user) || $this->isKepalaSekolah($user)) {
            return true;
        }

        $guruId = $this->getGuruId($user);
        return $presensi->guruMapel?->guru_staf_id === $guruId;
    }

    public function create(User $user): bool
    {
        // Kesiswaan dan Kepsek TIDAK BISA input (create)
        if ($this->isWakaKesiswaan($user) || $this->isKepalaSekolah($user)) {
            return false;
        }

        // Admin bisa input
        if ($this->isAdmin($user)) {
            return true;
        }

        // Guru bisa input jika memiliki ID Guru
        return !is_null($this->getGuruId($user));
    }

    public function update(User $user, PresensiGuruMapel $presensi): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        // Kesiswaan hanya boleh memantau, jika ingin diizinkan update ganti ke true
        if ($this->isWakaKesiswaan($user)) {
            return false; 
        }

        if ($this->isKepalaSekolah($user)) {
            return false;
        }

        $guruId = $this->getGuruId($user);
        return $presensi->guruMapel?->guru_staf_id === $guruId;
    }

    public function delete(User $user, PresensiGuruMapel $presensi): bool
    {
        // Hanya Admin yang bisa menghapus
        // Kesiswaan, Kepsek, dan Guru tidak bisa hapus
        return $this->isAdmin($user);
    }
}