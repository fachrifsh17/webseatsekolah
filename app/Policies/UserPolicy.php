<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Admin selalu memiliki akses penuh (Super Admin bypass).
     */
    public function before(User $user, $capability)
    {
        // Mengecek current_role atau role permanen Admin
        if ($user->current_role === 'Admin' || $user->hasRole('Admin')) {
            return true; 
        }
    }

    /**
     * Logic untuk ganti role (Switch Role)
     */
    public function switchRole(User $user, $targetRole): bool
    {
        // Siswa dilarang berpindah role ke manapun (jika memang tidak punya role lain)
        // Dan target role harus dimiliki oleh user di tabel relasi
        if ($user->current_role === 'Siswa') {
            return false;
        }

        return $user->roles()->where('role_name', $targetRole)->exists();
    }

    /**
     * Update profil sendiri
     */
    public function update(User $user, User $model): Response
    {
        // 1. Pastikan yang diupdate adalah dirinya sendiri
        if ($user->id !== $model->id) {
            return Response::deny('Anda hanya diizinkan mengubah data profil milik sendiri.');
        }

        // 2. Cek current_role yang dilarang (Siswa)
        if ($user->current_role === 'Siswa') {
            return Response::deny('Akses ditolak. Siswa tidak diizinkan mengubah profil melalui fitur ini.');
        }

        // 3. Izinkan jika role aktif adalah Guru atau Admin (Orang Tua sesuai kodingan lama Anda dilarang di update profil?)
        // Jika Orang Tua juga boleh update, hapus blok ini. 
        // Namun jika hanya Guru dan Admin:
        $allowedRoles = ['Guru', 'Admin', 'Orangtua']; // Sesuaikan list ini
        if (!in_array($user->current_role, $allowedRoles)) {
            return Response::deny('Role Anda saat ini tidak memiliki izin untuk update profil.');
        }

        return Response::allow();
    }

    public function view(User $user, User $model): bool
    {
        // Semua role bisa melihat profilnya sendiri
        return $user->id === $model->id;
    }

    // Method lainnya dibuat false secara default karena biasanya ditangani Admin
    public function viewAny(User $user): bool { return false; }
    public function create(User $user): bool { return false; }
    public function delete(User $user, User $model): bool { return false; }
}