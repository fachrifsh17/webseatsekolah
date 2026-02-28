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

    public function updateSelf(User $user, User $model): Response
    {
        if ($user->id !== $model->id) {
            return Response::deny('Anda tidak diizinkan mengubah data orang lain.');
        }

        return Response::allow();
    }

    public function switchRole(User $user, $targetRole): Response
    {
        if (!$user->roles()->where('role_name', $targetRole)->exists()) {
            return Response::deny("Role $targetRole tidak ditemukan pada user Anda.");
        }

        if ($user->current_role === 'Siswa') {
            return Response::deny('Siswa tidak diizinkan berpindah role.');
        }
        
        $allowedRoles = ['Guru', 'Orangtua', 'Admin'];
        if (!in_array($targetRole, $allowedRoles)) {
            return Response::deny('Target role tidak valid.');
        }

        return Response::allow();
    }

    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id;
    }

    public function viewAny(User $user): bool { return false; }
    public function create(User $user): bool { return false; }
    public function update(User $user, User $model): bool { return false; }
    public function delete(User $user, User $model): bool { return false; }
}