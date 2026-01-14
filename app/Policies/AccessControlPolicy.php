<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Siswa;
use App\Models\Kelas;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Request;

class AccessControlPolicy
{
    public function before(?User $user, string $ability): ?bool
    {
        if (!$user) return null;

        if (method_exists($user, 'hasRole')) {
            $isAdmin = $user->hasRole('admin') || $user->hasRole('Admin') || $user->hasRole('ADMIN');
            $isRouteAdmin = Request::is('api/admin/*') || Request::is('admin/*');

            if ($isAdmin && $isRouteAdmin) {
                return true;
            }
        }

        return null;
    }

    protected function getUserGuruIdSafe(User $user): ?string
    {
        return (string) ($user->guruStaf?->id ?? $user->guru?->id);
    }

    public function viewAny(User $user): Response
    {
        return $user->guruStaf !== null 
            ? Response::allow() 
            : Response::deny('Akses ditolak.');
    }

    public function view(User $user, $model): Response
    {
        // Logika khusus untuk Siswa (hanya wali kelas yang bisa melihat detail siswa)
        if ($model instanceof Siswa) {
            $userGuruId = $this->getUserGuruIdSafe($user);
            $isWali = Kelas::where('id', $model->class_id ?? $model->kelas_id)
                           ->where('wali_kelas_id', $userGuruId)
                           ->exists();
                           
            return $isWali ? Response::allow() : Response::deny('Anda bukan wali kelas untuk siswa ini.');
        }

        return Response::allow();
    }

    public function create(User $user): Response
    {
        return $user->guruStaf !== null ? Response::allow() : Response::deny('Akses terbatas untuk staff.');
    }

    public function update(User $user, $model): Response
    {
        return $this->view($user, $model);
    }

    public function delete(User $user, $model): Response
    {
        return Response::deny('Hanya Admin yang dapat menghapus data melalui panel admin.');
    }
}