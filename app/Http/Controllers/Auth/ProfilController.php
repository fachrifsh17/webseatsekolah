<?php

namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, Storage};
use App\Http\Requests\{UpdateProfileRequest, ChangePasswordRequest};
use Symfony\Component\HttpFoundation\Response;
use App\Models\User; 

class ProfilController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles', 'siswa', 'guruStaf']);

        $foto = null;
        if ($user->roles->contains('role_name', 'Siswa')) {
            $foto = $user->siswa?->foto;
        } elseif ($user->roles->contains('role_name', 'Guru')) {
            $foto = $user->guruStaf?->foto;
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'           => (string) $user->id,
                'username'     => $user->username,
                'nama_lengkap' => $user->nama_lengkap,
                'roles'        => $user->roles->pluck('role_name'),
                'foto'         => $foto,
            ]
        ]);
    }

    public function updateFoto(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
   
        $this->authorize('updateSelf', $user);

        if ($request->hasFile('foto')) {
            $model = null;
            if ($user->roles->contains('role_name', 'Siswa')) {
                $model = $user->siswa;
            } elseif ($user->roles->contains('role_name', 'Guru')) {
                $model = $user->guruStaf;
            }

            if (!$model) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil detail tidak ditemukan atau role tidak diizinkan.'
                ], Response::HTTP_FORBIDDEN);
            }

            if ($model->foto) {
                Storage::disk('public')->delete($model->foto);
            }

            $path = $request->file('foto')->store('foto', 'public');
            $model->update(['foto' => $path]);
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