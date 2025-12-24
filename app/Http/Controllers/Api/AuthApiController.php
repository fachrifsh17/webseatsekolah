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

class AuthApiController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'username'     => 'required|string|unique:users,username',
            'password'     => 'required|string|min:6|confirmed',
            'nama_lengkap' => 'required|string',
            'role_id'      => 'required|integer', 
        ]);

        $user = User::create([
            'username'     => $request->username,
            'password'     => Hash::make($request->password),
            'nama_lengkap' => $request->nama_lengkap,
            'role_id'      => $request->role_id,
            'is_active'    => 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil, akun sudah dibuat.',
            'user'    => new UserResource($user->load('role'))
        ], Response::HTTP_CREATED);
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Username atau password salah'
            ], 401);
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
            'user'          => new UserResource($user->load('role'))
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data'    => new UserResource($request->user()->load(['role', 'guru', 'siswa', 'orangtua']))
        ]);
    }

    public function refresh(Request $request)
    {
        $refreshToken = $request->input('refresh_token');

        if (!$refreshToken) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token tidak ditemukan'
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
                'message' => 'Sesi berakhir, silakan login kembali'
            ], 401);
        }

        $newAccessToken = Str::random(80);
        $tokenRecord->update([
            'token_hash' => hash('sha256', $newAccessToken),
            'expires_at' => now()->addHour()
        ]);

        return response()->json([
            'success'      => true,
            'access_token' => $newAccessToken
        ]);
    }
    
    public function logout(Request $request)
    {
        $token = $request->bearerToken();
        if ($token) {
            $hash = hash('sha256', $token);
            AuthToken::where('token_hash', $hash)->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil'
        ]);
    }
}