<?php

namespace App\Policies;

use App\Models\User;
use App\Models\TahunAjaran;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Log;

class AccessControlPolicy
{
    /**
     * Admin selalu lolos semua policy
     */
    public function before(?User $user, string $ability): ?bool
    {
        Log::debug('Policy before check', [
            'user_id' => $user?->id ?? null,
            'roles' => $user?->getRoleNames(),
            'ability' => $ability,
        ]);

        if (! $user) {
            return null;
        }

        // normalisasi ke lowercase agar konsisten dengan helper hasRole
        if ($user->hasRole('admin')) {
            Log::debug('Policy before: admin bypass', ['user_id' => $user->id]);
            return true;
        }

        return null;
    }

    /**
     * Guru bisa manage (CRUD) hanya jika dia wali kelas dari data tersebut.
     * Untuk model yang tidak punya relasi kelas (mis. TahunAjaran), izinkan guru.
     */
    public function manage(User $user, $model = null): Response
    {
        if ($user->hasRole('guru')) {
            // Jika model adalah TahunAjaran, izinkan (tidak relevan dengan wali kelas)
            if ($model instanceof TahunAjaran) {
                return Response::allow();
            }

            // Jika tidak ada model atau bukan object, izinkan (mis. create)
            if (! is_object($model)) {
                return Response::allow();
            }

            // Jika model tidak punya relasi kelas, izinkan
            if (! isset($model->kelas)) {
                return Response::allow();
            }

            // Jika model punya relasi kelas, cek wali kelas
            if (isset($model->kelas->wali_kelas_id)) {
                $userGuruId = $user->guru?->id;
                $waliId = $model->kelas->wali_kelas_id;

                if ($userGuruId !== null && (string) $userGuruId === (string) $waliId) {
                    return Response::allow();
                }

                Log::warning('Policy deny manage: not wali kelas', [
                    'user_id' => $user->id,
                    'user_guru_id' => $userGuruId ?? null,
                    'model_kelas_wali_id' => $waliId ?? null,
                    'model_class' => get_class($model),
                ]);

                return Response::deny('Anda bukan wali kelas untuk kelas ini.');
            }

            // fallback: izinkan
            return Response::allow();
        }

        Log::warning('Policy deny manage: role not guru', [
            'user_id' => $user->id,
            'roles' => $user->getRoleNames(),
            'model' => is_object($model) ? get_class($model) : $model,
        ]);

        return Response::deny('Akses ditolak.');
    }

    /**
     * Admin dan Guru bisa input poin
     */
    public function inputPoin(User $user): Response
    {
        if ($user->hasAnyRole(['admin', 'guru'])) {
            return Response::allow();
        }

        Log::warning('Policy deny inputPoin: insufficient role', [
            'user_id' => $user->id,
            'roles' => $user->getRoleNames(),
        ]);

        return Response::deny('Hanya Admin dan Guru yang dapat input poin.');
    }

    /**
     * Admin, Guru, dan Siswa bisa update profil
     */
    public function updateProfile(User $user): Response
    {
        if ($user->hasAnyRole(['admin', 'guru', 'siswa'])) {
            return Response::allow();
        }

        Log::warning('Policy deny updateProfile: insufficient role', [
            'user_id' => $user->id,
            'roles' => $user->getRoleNames(),
        ]);

        return Response::deny('Hanya Admin, Guru, dan Siswa yang dapat mengubah profil.');
    }

    /**
     * viewAny dan view untuk resource umum
     */
    public function viewAny(User $user): Response
    {
        if ($user->hasAnyRole(['admin', 'guru'])) {
            return Response::allow();
        }

        Log::warning('Policy deny viewAny: insufficient role', [
            'user_id' => $user->id,
            'roles' => $user->getRoleNames(),
        ]);

        return Response::deny('Anda tidak memiliki izin untuk melihat data.');
    }

    public function view(User $user, $model): Response
    {
        if ($user->hasAnyRole(['admin', 'guru', 'siswa'])) {
            return Response::allow();
        }

        Log::warning('Policy deny view: insufficient role', [
            'user_id' => $user->id,
            'roles' => $user->getRoleNames(),
            'model' => is_object($model) ? get_class($model) : $model,
        ]);

        return Response::deny('Anda tidak memiliki izin untuk melihat detail data.');
    }
}
