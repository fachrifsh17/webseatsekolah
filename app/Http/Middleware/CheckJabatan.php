<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckJabatan
{
    public function handle(Request $request, Closure $next, ...$jabatans): Response
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // 1. Cek apakah user login dan memiliki profil GuruStaf
        if (!$user || !$user->guruStaf) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized: Profil Guru tidak ditemukan'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // 2. AMBIL SEMUA JABATAN (Perbaikan Error Collection)
        // Kita ambil semua nama_jabatan dari koleksi strukturJabatan
        $userJabatanNames = $user->guruStaf->strukturJabatan->map(function($sj) {
            return $sj->jabatan?->nama_jabatan;
        })->filter()->toArray();

        if (empty($userJabatanNames)) {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden: Anda tidak memiliki jabatan struktural'
            ], Response::HTTP_FORBIDDEN);
        }

        // 3. Cek apakah ada salah satu jabatan user yang diizinkan
        // Kita bandingkan array jabatan user dengan array $jabatans dari middleware
        $hasAccess = !empty(array_intersect($userJabatanNames, $jabatans));

        if (!$hasAccess) {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden: Akses ditolak untuk jabatan ini',
                'jabatan_anda' => $userJabatanNames // Menampilkan semua jabatan yang dimiliki
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}