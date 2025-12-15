<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthApiController extends Controller
{
    public function login(Request $request)
    {
        $v = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($v->fails()) return response()->json(['success'=>false,'errors'=>$v->errors()], 422);

        $user = User::where('username', $request->username)->first();
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['success'=>false,'message'=>'Credensial tidak valid'], 401);
        }

        // Gunakan Sanctum token atau implementasi token Anda
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'token' => $token,
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->currentAccessToken()->delete();
        }
        return response()->json(['success'=>true,'message'=>'Logout berhasil']);
    }

    public function me(Request $request)
    {
        return response()->json(['success'=>true,'data'=>$request->user()]);
    }
}
