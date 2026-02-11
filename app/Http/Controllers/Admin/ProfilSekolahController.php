<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProfilSekolah;
use App\Http\Resources\ProfilSekolahResource;
use App\Http\Requests\UpdateProfilSekolahRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // Tambahkan ini
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class ProfilSekolahController extends Controller
{
    use AuthorizesRequests; // Gunakan trait otorisasi

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only(['update', 'destroy']);
        
        // Mengotomatisasi pengecekan Policy
        $this->authorizeResource(ProfilSekolah::class, 'profil_sekolah');
    }

    public function index(): JsonResponse
    {
        try {
            $profil = ProfilSekolah::with(['guruStaf'])->first();

            if (!$profil) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data profil tidak ditemukan'
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'data'    => new ProfilSekolahResource($profil)
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateProfilSekolahRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Logika Join Tabel Jabatan untuk mencari Kepala Sekolah tetap sama
        $kepsekOtomatis = DB::table('struktur_jabatan')
            ->join('jabatans', 'struktur_jabatan.jabatan_id', '=', 'jabatans.id')
            ->where('jabatans.slug', 'kepala-sekolah')
            ->select('struktur_jabatan.guru_staf_id')
            ->first();

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
                    'guru_staf_id'    => $kepsekOtomatis->guru_staf_id ?? ($validated['guru_staf_id'] ?? null),
                ]
            );

            DB::commit();

            return response()->json([
                'success'      => true,
                'message'      => 'Profil sekolah berhasil diperbarui',
                'notification' => 'Berhasil diperbarui',
                'data'         => new ProfilSekolahResource($profil->fresh(['guruStaf'])),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Admin Update Profil Error: ' . $e->getMessage());

            return response()->json([
                'success'      => false,
                'message'      => 'Gagal menyimpan profil sekolah',
                'notification' => 'Gagal menyimpan',
                'errors'       => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Admin Delete Profil Error: ' . $e->getMessage());

            return response()->json([
                'success'      => false,
                'message'      => 'Gagal menghapus profil sekolah',
                'notification' => 'Gagal dihapus',
                'errors'       => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}