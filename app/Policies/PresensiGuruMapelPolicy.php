<?php

namespace App\Policies;

use App\Models\PresensiGuruMapel;
use App\Models\User;
<<<<<<< HEAD
use Illuminate\Auth\Access\Response;

class PresensiGuruMapelPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PresensiGuruMapel $presensiGuruMapel): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PresensiGuruMapel $presensiGuruMapel): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PresensiGuruMapel $presensiGuruMapel): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PresensiGuruMapel $presensiGuruMapel): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PresensiGuruMapel $presensiGuruMapel): bool
    {
        return false;
    }
}
=======
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
>>>>>>> master
