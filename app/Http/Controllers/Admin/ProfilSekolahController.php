<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProfilSekolah;
use App\Http\Resources\ProfilSekolahResource;
use App\Http\Requests\UpdateProfilSekolahRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProfilSekolahController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['update', 'destroy']);
    }

    public function update(UpdateProfilSekolahRequest $request): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $profil = ProfilSekolah::updateOrCreate(
                ['id' => 1],
                [
                    'nama_sekolah'    => $validated['nama_sekolah'] ?? null,
                    'npsn'            => $validated['npsn'] ?? null,
                    'akreditasi'      => $validated['akreditasi'] ?? null,
                    'visi'            => $validated['visi'] ?? null,
                    'misi'            => $validated['misi'] ?? null,
                    'sejarah'         => $validated['sejarah'] ?? null,
                    'sambutan_kepsek' => $validated['sambutan_kepsek'] ?? null,
                    'guru_staf_id'    => $validated['guru_staf_id'] ?? null,
                ]
            );

            DB::commit();

            return response()->json([
                'success'      => true,
                'message'      => 'Profil sekolah berhasil diperbarui',
                'notification' => 'Berhasil diperbarui',
                'data'         => new ProfilSekolahResource($profil->fresh(['guruStaf'])),
            ], 200);
        } catch (Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success'      => false,
                'message'      => 'Gagal menyimpan profil sekolah',
                'notification' => 'Gagal menyimpan',
                'error'        => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(): JsonResponse
    {
        DB::beginTransaction();
        try {
            $profil = ProfilSekolah::find(1);
            if ($profil) {
                $profil->delete();
            }

            DB::commit();

            return response()->json([
                'success'      => true,
                'message'      => 'Profil sekolah berhasil dihapus',
                'notification' => 'Berhasil dihapus',
            ], 200);
        } catch (Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success'      => false,
                'message'      => 'Gagal menghapus profil sekolah',
                'notification' => 'Gagal menghapus',
                'error'        => $e->getMessage(),
            ], 500);
        }
    }
}
