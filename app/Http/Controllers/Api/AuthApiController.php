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
        ]);

        $plainToken = Str::random(80);
        AuthToken::create([
            'user_id'    => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(30),
            'revoked'    => false
        ]);

        return response()->json([
            'success'      => true,
            'message'      => 'Registrasi berhasil',
            'access_token' => $plainToken,
            'user'         => new UserResource($user)
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

        $plainToken = Str::random(80);
        AuthToken::create([
            'user_id'    => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(30),
            'revoked'    => false
        ]);

        return response()->json([
            'success'      => true,
            'message'      => 'Login berhasil',
            'access_token' => $plainToken,
            'user'         => new UserResource($user)
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->bearerToken();
        if ($token) {
            $hash = hash('sha256', $token);
            AuthToken::where('token_hash', $hash)->update(['revoked' => true]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil'
        ]);
    }
}