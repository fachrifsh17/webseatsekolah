<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProfilSekolah;
use App\Http\Resources\ProfilSekolahResource;
use App\Http\Requests\UpdateProfilSekolahRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\{DB, Log, Storage}; // Tambahkan Storage
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class ProfilSekolahController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['update', 'destroy']);
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
        $this->authorize('update', ProfilSekolah::class);

        $validated = $request->validated();

        // Ambil data kepsek otomatis dari struktur jabatan jika ada
        $kepsekOtomatis = DB::table('struktur_jabatan')
            ->join('jabatans', 'struktur_jabatan.jabatan_id', '=', 'jabatans.id')
            ->where('jabatans.slug', 'kepala-sekolah')
            ->select('struktur_jabatan.guru_staf_id')
            ->first();

        DB::beginTransaction();
        try {
            // Cari data profil lama atau buat baru (id 1)
            $profil = ProfilSekolah::find(1) ?? new ProfilSekolah();

            // --- Logika Upload Logo ---
            if ($request->hasFile('logo')) {
                // Hapus logo lama jika ada file baru dan file lama terdaftar
                if ($profil->logo && Storage::disk('public')->exists($profil->logo)) {
                    Storage::disk('public')->delete($profil->logo);
                }
                
                // Simpan file baru ke folder public/logos
                $path = $request->file('logo')->store('logos', 'public');
                $validated['logo'] = $path;
            }

            $profil->fill([
                'nama_sekolah'    => $validated['nama_sekolah'] ?? $profil->nama_sekolah,
                'cadis'           => $validated['cadis'] ?? $profil->cadis, // Tambahan
                'logo'            => $validated['logo'] ?? $profil->logo,   // Tambahan
                'npsn'            => $validated['npsn'] ?? $profil->npsn,
                'akreditasi'      => $validated['akreditasi'] ?? $profil->akreditasi,
                'visi'            => $validated['visi'] ?? $profil->visi,
                'misi'            => $validated['misi'] ?? $profil->misi,
                'sejarah'         => $validated['sejarah'] ?? $profil->sejarah,
                'sambutan_kepsek' => $validated['sambutan_kepsek'] ?? $profil->sambutan_kepsek,
                'guru_staf_id'    => $kepsekOtomatis->guru_staf_id ?? ($validated['guru_staf_id'] ?? $profil->guru_staf_id),
            ]);

            $profil->id = 1; // Paksa ID tetap 1
            $profil->save();

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
        $this->authorize('delete', ProfilSekolah::class);

        DB::beginTransaction();
        try {
            $profil = ProfilSekolah::find(1);
            if ($profil) {
                // Opsional: Hapus logo dari storage saat data dihapus
                if ($profil->logo) {
                    Storage::disk('public')->delete($profil->logo);
                }
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