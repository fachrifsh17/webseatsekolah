<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class LoginController extends Controller
{
    // --- WEB AUTHENTICATION (Session) ---

    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required',
            'password' => 'required'
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            $role = Auth::user()->role;

            if ($role === 'admin') {
                return redirect('/admin');
            }
            if ($role === 'guru') {
                return redirect('/guru');
            }
            
            // Handle role tidak valid
            Auth::logout();
            return back()->withErrors(['username' => 'Role pengguna tidak valid.']);
        }

        return back()->withErrors(['username' => 'Username atau password salah.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }

    // --- API AUTHENTICATION (Sanctum Token) ---

    public function apiLogin(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required',
            'password' => 'required'
        ]);

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Username atau password salah'
            ], Response::HTTP_UNAUTHORIZED); // 401
        }

        $user = Auth::user();
        
        // Buat token Sanctum
        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login API berhasil',
            'role' => $user->role,
            'token' => $token
        ], Response::HTTP_OK); // 200
    }

    public function apiLogout(Request $request)
    {
        // Hapus token yang sedang digunakan
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout API berhasil'
        ], Response::HTTP_OK); // 200
    }
}