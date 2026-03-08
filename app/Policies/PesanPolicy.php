<?php

namespace App\Policies;

use App\Models\Pesan;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PesanPolicy
{
    use HandlesAuthorization;

    /**
     * Menentukan apakah user bisa melihat daftar semua pesan.
     */
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    /**
     * Menentukan apakah user bisa melihat isi pesan detail.
     */
    public function view(User $user, Pesan $pesan): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    /**
     * Menentukan apakah user bisa mengubah status pesan (is_read).
     */
    public function update(User $user, Pesan $pesan): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    /**
     * Menentukan apakah user bisa menandai semua pesan terbaca.
     */
    public function markAllAsRead(User $user): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    /**
     * Menentukan apakah user bisa menghapus pesan.
     */
    public function delete(User $user, Pesan $pesan): bool
    {
        return $this->authorize($user, ['Admin'], ['Waka Humas']);
    }

    /**
     * Fungsi Internal: Logika pengecekan Role dan Jabatan.
     */
    protected function authorize(User $user, array $allowedRoles = [], array $allowedJabatans = []): bool
    {
        // 1. Cek berdasarkan Role (Tabel Roles)
        $hasRole = $user->roles->pluck('role_name')->contains(function ($roleName) use ($allowedRoles) {
            return in_array($roleName, $allowedRoles);
        });

        // 2. Cek berdasarkan Jabatan (Relasi GuruStaf -> StrukturJabatan -> Jabatan)
        $hasJabatan = false;
        if ($user->guruStaf) {
            $hasJabatan = $user->guruStaf->strukturJabatan
                ->pluck('jabatan.nama_jabatan')
                ->filter() // Menghapus nilai null
                ->contains(function ($jabatanName) use ($allowedJabatans) {
                    return in_array($jabatanName, $allowedJabatans);
                });
        }

        // Jika salah satu terpenuhi, akses diberikan (TRUE)
        return $hasRole || $hasJabatan;
    }
}