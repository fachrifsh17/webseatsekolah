<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response; // Import Response

class AuthApiController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $validated['username'])->first();
        
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Credensial tidak valid.'
            ], Response::HTTP_UNAUTHORIZED); // 401 Unauthorized
        }

        // Asumsi menggunakan Laravel Sanctum atau sejenisnya
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->only(['id', 'username', 'email']), // Filter data user
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ], Response::HTTP_OK); // 200 OK
    }

    public function logout(Request $request)
    {
        // Menghapus token saat ini
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.'
        ], Response::HTTP_OK); // 200 OK
    }

    public function me(Request $request)
    {
        // Mengembalikan data user yang sedang login (diasumsikan sudah melewati middleware auth:sanctum)
        return response()->json([
            'success' => true,
            'data' => $request->user()->only(['id', 'username', 'email'])
        ], Response::HTTP_OK); // 200 OK
    }
}