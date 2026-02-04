<?php

namespace App\Policies;

use App\Models\SekolahSetting;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SekolahSettingPolicy
{
    use HandlesAuthorization;

    public function before(User $user, $capability)
    {
        if ($user->hasRole('Admin')) {
            return true;
        }
    }

    public function viewAny(User $user): bool
    {
        return $user->jabatan === 'Kepala Sekolah';
    }

    public function view(User $user, SekolahSetting $sekolahSetting): bool
    {
        return $user->jabatan === 'Kepala Sekolah';
    }

    public function update(User $user, SekolahSetting $sekolahSetting): bool
    {
       
        return $user->jabatan === 'Kepala Sekolah';
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, SekolahSetting $sekolahSetting): bool
    {
        return false;
    }

    public function restore(User $user, SekolahSetting $sekolahSetting): bool
    {
        return false;
    }

    public function forceDelete(User $user, SekolahSetting $sekolahSetting): bool
    {
        return false;
    }
}