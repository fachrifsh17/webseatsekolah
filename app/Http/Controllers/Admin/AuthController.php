<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\AuthToken;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token')->only(['logout']);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak ditemukan pada header Authorization.'
            ], 400);
        }

        $hash = hash('sha256', $token);

        $updated = AuthToken::where('token_hash', $hash)->update([
            'revoked' => true
        ]);

        if (! $updated) {
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