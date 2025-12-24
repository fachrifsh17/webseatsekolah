<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class AccessControlPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === 'Admin') {
            return true;
        }
        return null;
    }

    // Guru bisa manage (CRUD) hanya jika dia adalah wali kelas dari data tersebut
    public function manage(User $user, $model = null): Response
    {
        if ($user->role === 'Guru') {
            // Jika sedang mengecek data spesifik (misal: Presensi atau Siswa)
            if ($model && isset($model->kelas)) {
                return $user->guru_staf->id === $model->kelas->wali_kelas_id
                    ? Response::allow()
                    : Response::deny('Anda bukan wali kelas untuk kelas ini.');
            }
            return Response::allow();
        }

        return Response::deny('Akses Ditolak.');
    }

    // Semua Guru bisa input Poin tanpa batasan Wali Kelas (sesuai permintaan Anda)
    public function inputPoin(User $user): bool
    {
        return in_array($user->role, ['Admin', 'Guru']);
    }

    public function updateProfile(User $user): Response
    {
        return in_array($user->role, ['Admin', 'Guru', 'Siswa'])
            ? Response::allow()
            : Response::deny('Hanya Admin, Guru, dan Siswa yang dapat mengubah profil.');
    }
}