<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        // 1. Pastikan user sudah login
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Silakan login terlebih dahulu.'
            ], 401);
        }

        /**
         * 2. Ambil SEMUA nama role dari relasi many-to-many.
         * Kita gunakan pluck() untuk mengambil semua 'role_name'[cite: 3, 134].
         */
        $userRoles = $user->roles->pluck('role_name')->toArray(); 

        if (empty($userRoles)) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak memiliki role apa pun.'
            ], 403);
        }

        // 3. Pengecekan Case-Insensitive
        // Kita cek apakah ada salah satu role user yang cocok dengan role yang diizinkan di route
        $allowedRoles = array_map('strtolower', $roles);
        $userRolesLower = array_map('strtolower', $userRoles);

        // array_intersect mengecek apakah ada role yang beririsan
        if (empty(array_intersect($userRolesLower, $allowedRoles))) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses. Role Anda: ' . implode(', ', $userRoles)
            ], 403);
        }

        return $next($request);
    }
}