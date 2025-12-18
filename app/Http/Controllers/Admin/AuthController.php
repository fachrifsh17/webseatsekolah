<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\AuthToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AuthController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth.token', only: ['logout']),
        ];
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        $hash = hash('sha256', $token);

        AuthToken::where('token_hash', $hash)->update([
            'revoked' => true
        ]);

        Auth::logout();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil logout, token telah dicabut.'
        ]);
    }
}