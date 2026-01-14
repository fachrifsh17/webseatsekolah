<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\AuthToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

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
            'data' => new UserResource(
                $user->load(['roles', 'guruStaf', 'siswa', 'orangtua'])
            )
        ], Response::HTTP_OK);
    }

    public function refresh(Request $request): JsonResponse
    {
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        $hash = hash('sha256', $request->input('refresh_token'));

        $tokenRecord = AuthToken::where('refresh_token', $hash)
            ->where('refresh_expires_at', '>', now())
            ->where('revoked', 0)
            ->first();

        if (!$tokenRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token tidak valid atau sudah kadaluwarsa.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $newAccessToken = Str::random(80);

        $tokenRecord->update([
            'token_hash' => hash('sha256', $newAccessToken),
            'expires_at' => now()->addHour()
        ]);

        Log::info('Access token refreshed', [
            'auth_token_id' => (string)$tokenRecord->id,
            'user_id'       => (string)$tokenRecord->user_id
        ]);

        return response()->json([
            'success'      => true,
            'access_token' => $newAccessToken,
            'token_type'   => 'Bearer',
            'expires_in'   => 3600
        ], Response::HTTP_OK);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak ditemukan pada header Authorization.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $hash = hash('sha256', $token);

        $updated = AuthToken::where('token_hash', $hash)->update([
            'revoked' => 1
        ]);

        if (!$updated) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid atau sudah dicabut.'
            ], Response::HTTP_BAD_REQUEST);
        }

        Auth::logout();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil logout, token telah dicabut.'
        ], Response::HTTP_OK);
    }
}
