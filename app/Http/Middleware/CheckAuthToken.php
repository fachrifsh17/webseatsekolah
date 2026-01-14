<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\AuthToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Auth\Authenticatable;

class CheckAuthToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return response()->noContent();
        }

        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak ditemukan. Silakan login terlebih dahulu.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $tokenHash = hash('sha256', $token);

        $authToken = AuthToken::with([
                'user.roles',
                'user.guruStaf',
                'user.siswa',
                'user.orangtua'
            ])
            ->where('token_hash', $tokenHash)
            ->where('revoked', false)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$authToken || !$authToken->user) {
            return response()->json([
                'success'    => false,
                'message'    => 'Token kadaluwarsa atau tidak valid.',
                'error_code' => 'TOKEN_EXPIRED'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = $authToken->user;

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda tidak aktif.'
            ], Response::HTTP_FORBIDDEN);
        }

        if (!$user instanceof Authenticatable) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak dapat diautentikasi.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            Auth::setUser($user);
            $request->setUserResolver(fn () => $user);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengatur autentikasi pengguna.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->ensureResponse($next($request));
    }

    protected function ensureResponse($response): Response
    {
        if ($response instanceof Response || $response instanceof \Illuminate\Http\JsonResponse) {
            return $response;
        }

        if (is_array($response) || is_object($response)) {
            return response()->json($response);
        }

        return response((string) $response);
    }
}
