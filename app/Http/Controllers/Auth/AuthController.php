<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\{AuthToken, User};
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\{Log, DB};
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token')->only(['logout', 'me', 'switchRole']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('view', $user);

        // Memuat relasi riwayatKelas untuk siswa dan kelas aktif untuk guru
        $user->load([
            'roles',
            'guruStaf.strukturJabatan.jabatan',
            'guruStaf.kelas' => function($q) {
                $q->wherePivot('is_active', 1);
            },
            'siswa.riwayatKelas' => function($q) {
                $q->where('is_active', 1)->with('kelas');
            },
            'orangtua'
        ]);

        $fotoPath = null;
        if ($user->guruStaf && $user->guruStaf->foto) {
            $rawFoto = $user->guruStaf->foto;
            // Menyeragamkan path guru ke folder guru/
            $rawFoto = str_replace('uploads/guru/', '', $rawFoto);
            $fotoPath = 'guru/' . ltrim($rawFoto, '/');
        } elseif ($user->siswa && $user->siswa->foto) {
            $rawFoto = $user->siswa->foto;
            // Menyeragamkan path siswa langsung ke folder siswa/ (tanpa subfolder foto/)
            $rawFoto = str_replace(['uploads/siswa/',''], '', $rawFoto);
            $fotoPath = 'siswa/' . ltrim($rawFoto, '/');
        }

        // Generate URL Foto berdasarkan folder uploads/
        if ($fotoPath) {
            // Menghapus prefix 'uploads/' jika ada di DB untuk mencegah double path
            $cleanPath = str_replace('uploads/', '', $fotoPath);
            $user->foto_url = asset('uploads/' . $cleanPath);
        } else {
            $user->foto_url = asset('images/default-avatar.png');
        }

        return response()->json([
            'success' => true,
            'data' => new UserResource($user)
        ], Response::HTTP_OK);
    }

    public function switchRole(Request $request): JsonResponse
    {
        $request->validate([
            'role' => 'required|string'
        ]);

        $user = $request->user();
        $targetRole = $request->role;

        $hasRole = $user->roles()->where('role_name', $targetRole)->exists();
        if (!$hasRole) {
            return response()->json([
                'success' => false,
                'message' => "Role $targetRole tidak ditemukan pada user."
            ], Response::HTTP_FORBIDDEN);
        }

        $this->authorize('switchRole', [$user, $targetRole]);

        try {
            $user->update([
                'current_role' => $targetRole
            ]);

            return response()->json([
                'success' => true,
                'message' => "Berhasil pindah ke role: $targetRole",
                'current_role' => $targetRole
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengganti role.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
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
                'message' => 'Token tidak ditemukan.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $hash = hash('sha256', $token);

        $updated = AuthToken::where('token_hash', $hash)->update([
            'revoked' => 1
        ]);

        if (!$updated) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid.'
            ], Response::HTTP_BAD_REQUEST);
        }

        Auth::logout();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil logout.'
        ], Response::HTTP_OK);
    }
}