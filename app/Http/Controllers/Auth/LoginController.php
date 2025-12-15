<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    // ✅ Halaman login web
    public function showLoginForm()
    {
        return view('auth.login');
    }

    // ✅ Login WEB (Admin & Guru) pakai USERNAME
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required',
            'password' => 'required'
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            // ✅ Cek role
            if (Auth::user()->role === 'admin') {
                return redirect('/admin');
            }

            if (Auth::user()->role === 'guru') {
                return redirect('/guru');
            }

            // ✅ Role tidak valid
            Auth::logout();
            return back()->withErrors(['username' => 'Role tidak valid.']);
        }

        return back()->withErrors([
            'username' => 'Username atau password salah.',
        ]);
    }

    // ✅ Logout WEB
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }

    // ✅ Login API (token) pakai USERNAME
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
            ], 401);
        }

        $user = Auth::user();

        // ✅ Buat token Sanctum
        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'role' => $user->role,
            'token' => $token
        ]);
    }

    // ✅ Logout API (hapus token)
    public function apiLogout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil'
        ]);
    }
}
