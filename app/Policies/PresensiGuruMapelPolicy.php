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

    private function isFullAccess(User $user): bool
    {
        $isAdmin = method_exists($user, 'hasRole') && ($user->hasRole('admin') || $user->hasRole('Admin'));

        $jabatanUser = $user->guruStaf?->strukturJabatan ?? collect();
        $isWakaKesiswaan = $jabatanUser->contains(function ($sj) {
            return ($sj->jabatan?->nama_jabatan ?? '') === 'Waka Kesiswaan';
        });

        return $isAdmin || $isWakaKesiswaan;
    }

    private function isKepalaSekolah(User $user): bool
    {
        $jabatanUser = $user->guruStaf?->strukturJabatan ?? collect();
        return $jabatanUser->contains(function ($sj) {
            return ($sj->jabatan?->nama_jabatan ?? '') === 'Kepala Sekolah';
        });
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PresensiGuruMapel $presensi): bool
    {
        if ($this->isFullAccess($user) || $this->isKepalaSekolah($user)) {
            return true;
        }

        $guruId = $this->getGuruId($user);
        return $presensi->guruMapel?->guru_staf_id === $guruId;
    }

    public function create(User $user): bool
    {
        if ($this->isFullAccess($user)) {
            return true;
        }

        return !is_null($this->getGuruId($user));
    }

    public function update(User $user, PresensiGuruMapel $presensi): bool
    {
        if ($this->isFullAccess($user)) {
            return true;
        }

        if ($this->isKepalaSekolah($user)) {
            return false;
        }

        $guruId = $this->getGuruId($user);
        return $presensi->guruMapel?->guru_staf_id === $guruId;
    }

    public function delete(User $user, PresensiGuruMapel $presensi): bool
    {
        return $this->isFullAccess($user);
    }
}