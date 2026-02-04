<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\ChangePasswordRequest;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User; 

class ProfilController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $foto = null;
        if ($user->roles->contains('role_name', 'Siswa') && $user->siswa) {
            $foto = $user->siswa->foto;
        } elseif ($user->roles->contains('role_name', 'Guru') && $user->guruStaf) {
            $foto = $user->guruStaf->foto;
        }

        $roles = $user->roles()->pluck('role_name')->toArray();

        return response()->json([
            'success' => true,
            'data'    => [
                'id'           => (string) $user->id,
                'username'     => $user->username,
                'nama_lengkap' => $user->nama_lengkap,
                'roles'        => $roles,
                'foto'         => $foto,
            ]
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
   
        $this->authorize('updateSelf', $user);

        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('foto', 'public');

            if ($user->roles->contains('role_name', 'Siswa') && $user->siswa) {
                if ($user->siswa->foto) {
                    Storage::disk('public')->delete($user->siswa->foto);
                }
                $user->siswa->update(['foto' => $path]);
            } elseif ($user->roles->contains('role_name', 'Guru') && $user->guruStaf) {
                if ($user->guruStaf->foto) {
                    Storage::disk('public')->delete($user->guruStaf->foto);
                }
                $user->guruStaf->update(['foto' => $path]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Role ini tidak diizinkan untuk mengganti foto profil.'
                ], Response::HTTP_FORBIDDEN);
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

        $this->authorize('updateSelf', $user);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password lama tidak sesuai.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
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