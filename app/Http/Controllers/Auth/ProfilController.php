<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, File};
use Illuminate\Support\Str; // Tambahkan ini untuk Str::random
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
            $folder = '';

            // Tentukan folder tujuan berdasarkan role
            if ($user->roles->contains('role_name', 'Siswa')) {
                $model = $user->siswa;
                $folder = 'siswa';
            } elseif ($user->roles->contains('role_name', 'Guru') || $user->roles->contains('role_name', 'Admin')) {
                // Diseragamkan untuk Guru/Staff menggunakan folder guru
                $model = $user->guruStaf;
                $folder = 'guru';
            }

            if (!$model) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil detail tidak ditemukan atau role tidak diizinkan.'
                ], Response::HTTP_FORBIDDEN);
            }

            // Path fisik ke public/uploads/
            $destinationPath = public_path('uploads/' . $folder);

            // Pastikan folder exist
            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }

            // Hapus foto lama agar tidak menumpuk di public/uploads
            if ($model->foto) {
                // Kita ambil nama filenya saja
                $oldFileName = basename($model->foto);
                $oldFilePath = $destinationPath . '/' . $oldFileName;
                
                if (File::exists($oldFilePath)) {
                    File::delete($oldFilePath);
                }
            }

            // Proses simpan file menggunakan move()
            $file = $request->file('foto');
            $fileName = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            
            // File akan masuk ke public/uploads/siswa atau public/uploads/guru
            $file->move($destinationPath, $fileName);

            // Simpan ke database dengan format: 'siswa/namafile.jpg' atau 'guru/namafile.jpg'
            // Ini akan sinkron dengan logic di UserResource dan AuthController
            $model->update(['foto' => $folder . '/' . $fileName]);
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