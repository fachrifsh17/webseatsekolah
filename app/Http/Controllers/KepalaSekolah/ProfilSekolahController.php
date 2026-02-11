<?php

namespace App\Http\Controllers\KepalaSekolah;

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
    use AuthorizesRequests; // Tambahkan ini

    public function __construct()
    {
        $this->middleware('auth.token');
        // Middleware log admin tetap dipertahankan untuk audit trail
        $this->middleware('Log.aktivitas')->only('update'); 
    }

    public function index(): JsonResponse
    {
        // Otorisasi: Kepsek boleh melihat profil sekolah
        $this->authorize('viewAny', ProfilSekolah::class);

        try {
            $profil = ProfilSekolah::with(['guruStaf'])->first();

            if (!$profil) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data profil sekolah tidak ditemukan'
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'data'    => new ProfilSekolahResource($profil)
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateProfilSekolahRequest $request): JsonResponse
    {
        // Otorisasi: Cek apakah Kepsek boleh memperbarui profil
        $this->authorize('update', ProfilSekolah::class);

        $validated = $request->validated();

        // Logika otomatis mengambil ID Guru yang menjabat Kepala Sekolah
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
                    // Prioritaskan kepsek dari struktur_jabatan, jika tidak ada baru dari input
                    'guru_staf_id'    => $kepsekOtomatis->guru_staf_id ?? ($validated['guru_staf_id'] ?? null),
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Profil dan Sambutan berhasil diperbarui',
                'data'    => new ProfilSekolahResource($profil->fresh(['guruStaf'])),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Update Profil Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal memperbarui profil'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}