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
            return response()->noContent(204);
        }

        try {
            $token = $request->bearerToken();

            if (empty($token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token tidak ditemukan. Silakan login terlebih dahulu.'
                ], 401);
            }

            $tokenHash = hash('sha256', $token);

            $authToken = AuthToken::with([
                    'user.roles',
                    'user.guru',
                    'user.siswa',
                    'user.orangtua'
                ])
                ->where('token_hash', $tokenHash)
                ->where('revoked', false)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->first();

            if (!$authToken || !$authToken->user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token kadaluwarsa atau tidak valid.',
                    'error_code' => 'TOKEN_EXPIRED'
                ], 401);
            }

            if (empty($authToken->user->is_active) || !$authToken->user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun Anda tidak aktif.'
                ], 403);
            }

            $user = $authToken->user;

            if (! $user instanceof Authenticatable) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun tidak dapat diautentikasi.'
                ], 500);
            }

            try {
                $guardName = config('auth.defaults.guard') ?? null;
                if ($guardName) {
                    $guard = Auth::guard($guardName);
                    if (is_object($guard) && method_exists($guard, 'setUser')) {
                        $guard->setUser($user);
                    } else {
                        Auth::setUser($user);
                    }
                } else {
                    Auth::setUser($user);
                }

                $request->setUserResolver(fn () => $user);
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengatur autentikasi pengguna.'
                ], 500);
            }

            $response = $next($request);

            return $this->ensureResponse($response);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server saat memproses autentikasi.'
            ], 500);
        }
    }

    protected function ensureResponse($response): Response
    {
        if ($response instanceof Response) {
            return $response;
        }

        if ($response instanceof \Illuminate\Http\JsonResponse) {
            return $response;
        }

        if (is_array($response) || is_object($response)) {
            return response()->json($response);
        }

        return response((string) $response);
    }
}
