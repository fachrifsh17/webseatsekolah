<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\AuthToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Http\Resources\UserResource;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token')->only(['logout', 'me']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => new UserResource($user)
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $refreshToken = $request->input('refresh_token');

        if (!$refreshToken) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token diperlukan.'
            ], 400);
        }

        $hash = hash('sha256', $refreshToken);

        $tokenRecord = AuthToken::where('refresh_token', $hash)
            ->where('refresh_expires_at', '>', now())
            ->where('revoked', false)
            ->first();

        if (!$tokenRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token tidak valid atau sudah kadaluwarsa.'
            ], 401);
        }

        $newAccessToken = Str::random(80);

        $tokenRecord->update([
            'token_hash' => hash('sha256', $newAccessToken),
            'expires_at' => now()->addHour()
        ]);

        return response()->json([
            'success' => true,
            'access_token' => $newAccessToken,
            'token_type' => 'Bearer'
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak ditemukan pada header Authorization.'
            ], 400);
        }

        $hash = hash('sha256', $token);

        $updated = AuthToken::where('token_hash', $hash)->update([
            'revoked' => true
        ]);

        if (!$updated) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid atau sudah dicabut.'
            ], 400);
        }

        Auth::logout();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil logout, token telah dicabut.'
        ]);
    }
}