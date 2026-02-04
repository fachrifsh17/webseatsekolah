<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    use HandlesAuthorization;

    public function before(User $user, $capability)
    {
        if ($user->hasRole('Admin')) {
            return true;
        }
    }

    public function updateSelf(User $user, User $targetUser): Response
    {
        if ($user->id !== $targetUser->id) {
            return Response::deny('Anda hanya diizinkan mengubah data profil milik sendiri.');
        }

        if ($user->hasAnyRole(['orangtua', 'Orang Tua', 'ortu'])) {
            return Response::deny('Akses ditolak. Orang tua tidak diizinkan mengubah profil melalui fitur ini.');
        }

        return Response::allow();
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $model): bool
    {
        return $user->id === $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function restore(User $user, User $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}