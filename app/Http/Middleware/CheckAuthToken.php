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

        $authToken = AuthToken::with('user')
            ->where('token_hash', hash('sha256', $token))
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$authToken || !$authToken->user) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid, expired, atau sudah logout.'
            ], 401);
        }

        if (!$authToken->user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda tidak aktif.'
            ], 403);
        }

        Auth::setUser($authToken->user);

        return $next($request);
    }
}