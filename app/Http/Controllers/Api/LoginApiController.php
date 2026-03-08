<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuthToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Http\Resources\UserResource;
use Symfony\Component\HttpFoundation\Response;

class LoginApiController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        try {
            $user = User::with('roles')->where('username', $request->username)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Username atau password salah'
                ], Response::HTTP_UNAUTHORIZED);
            }

            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun anda tidak aktif'
                ], Response::HTTP_FORBIDDEN);
            }

            AuthToken::where('user_id', $user->id)->delete();

            $plainAccessToken = Str::random(80);
            $plainRefreshToken = Str::random(80);

            AuthToken::create([
                'user_id'            => $user->id,
                'token_hash'         => hash('sha256', $plainAccessToken),
                'refresh_token'      => hash('sha256', $plainRefreshToken),
                'expires_at'         => now()->addHour(),
                'refresh_expires_at' => now()->addDays(30),
                'revoked'            => false
            ]);

            return response()->json([
                'success'       => true,
                'message'       => 'Login berhasil',
                'access_token'  => $plainAccessToken,
                'refresh_token' => $plainRefreshToken,
                'user'          => new UserResource($user)
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error'   => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}