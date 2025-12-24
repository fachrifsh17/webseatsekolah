<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\ChangePasswordRequest;

class ProfilController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $foto = null;
        if ($user->role->name === 'Siswa' && $user->siswa) {
            $foto = $user->siswa->foto;
        } elseif ($user->role->name === 'Guru' && $user->guruStaf) {
            $foto = $user->guruStaf->foto;
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'           => $user->id,
                'username'     => $user->username,
                'nama_lengkap' => $user->nama_lengkap,
                'role'         => $user->role->name,
                'foto'         => $foto,
            ]
        ]);
    }
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('foto', 'public');

            if ($user->role->name === 'Siswa' && $user->siswa) {
                if ($user->siswa->foto) {
                    Storage::disk('public')->delete($user->siswa->foto);
                }
                $user->siswa->update(['foto' => $path]);
            } elseif ($user->role->name === 'Guru' && $user->guruStaf) {
                if ($user->guruStaf->foto) {
                    Storage::disk('public')->delete($user->guruStaf->foto);
                }
                $user->guruStaf->update(['foto' => $path]);
            } else {
                // Role lain tidak boleh update foto
                return response()->json([
                    'success' => false,
                    'message' => 'Role ini tidak diizinkan untuk mengganti foto profil.'
                ], 403);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Foto profil berhasil diperbarui.'
        ]);
    }
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password lama tidak sesuai.'
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah.'
        ]);
    }
}