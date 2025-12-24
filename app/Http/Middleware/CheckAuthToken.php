<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\AuthToken; 
use Illuminate\Support\Facades\Auth;

class CheckAuthToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak ditemukan. Silakan login terlebih dahulu.'
            ], 401);
        }

        // PERUBAHAN: Menambahkan eager loading role, guru, siswa, dan ortu
        $authToken = AuthToken::with(['user.role', 'user.guru', 'user.siswa', 'user.orangtua'])
            ->where('token_hash', hash('sha256', $token))
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$authToken || !$authToken->user) {
            return response()->json([
                'success' => false,
                'message' => 'Token kadaluwarsa atau tidak valid.',
                'error_code' => 'TOKEN_EXPIRED'
            ], 401);
        }

        if ((int) $authToken->user->is_active === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda tidak aktif.'
            ], 403);
        }

        // Set user ke sistem Auth Laravel
        Auth::setUser($authToken->user);
        
        // PENTING: Memastikan data user di request sudah membawa data relasi (role)
        $request->setUserResolver(fn () => $authToken->user);

        return $next($request);
    }
}