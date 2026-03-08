<?php

namespace App\Http\Controllers\KepalaSekolah;

use App\Http\Controllers\Controller;
use App\Models\ProfilSekolah;
use App\Http\Resources\ProfilSekolahResource;
use App\Http\Requests\UpdateProfilSekolahRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        // Disesuaikan ke uploads/profil sesuai Resource sebelumnya
        $targetPath = public_path('uploads/profil');

        $kepsekOtomatis = DB::table('struktur_jabatan')
            ->join('jabatans', 'struktur_jabatan.jabatan_id', '=', 'jabatans.id')
            ->where('jabatans.slug', 'kepala-sekolah')
            ->select('struktur_jabatan.guru_staf_id')
            ->first();

        DB::beginTransaction();
        try {
            $profil = ProfilSekolah::find(1) ?? new ProfilSekolah();

            // Logika Upload Logo Sekolah
            if ($request->hasFile('logo')) {
                if ($profil->logo) {
                    $oldPath = $targetPath . '/' . str_replace('uploads/profil/', '', $profil->logo);
                    if (file_exists($oldPath)) @unlink($oldPath);
                }

                $file = $request->file('logo');
                $fileName = 'logo_' . time() . '_' . $file->getClientOriginalName();
                $file->move($targetPath, $fileName);
                $validated['logo'] = $fileName;
            }

            // Logika Upload Logo Provinsi
            if ($request->hasFile('logo_provinsi')) {
                if ($profil->logo_provinsi) {
                    $oldProvPath = $targetPath . '/' . str_replace('uploads/profil/', '', $profil->logo_provinsi);
                    if (file_exists($oldProvPath)) @unlink($oldProvPath);
                }

                $fileProv = $request->file('logo_provinsi');
                $fileNameProv = 'prov_' . time() . '_' . $fileProv->getClientOriginalName();
                $fileProv->move($targetPath, $fileNameProv);
                $validated['logo_provinsi'] = $fileNameProv;
            }

            $profil->fill([
                'nama_sekolah'    => $validated['nama_sekolah'] ?? $profil->nama_sekolah,
                'cadis'           => $validated['cadis'] ?? $profil->cadis,
                'logo'            => $validated['logo'] ?? $profil->logo,
                'logo_provinsi'   => $validated['logo_provinsi'] ?? $profil->logo_provinsi,
                'npsn'            => $validated['npsn'] ?? $profil->npsn,
                'akreditasi'      => $validated['akreditasi'] ?? $profil->akreditasi,
                'visi'            => $validated['visi'] ?? $profil->visi,
                'misi'            => $validated['misi'] ?? $profil->misi,
                'sejarah'         => $validated['sejarah'] ?? $profil->sejarah,
                'sambutan_kepsek' => $validated['sambutan_kepsek'] ?? $profil->sambutan_kepsek,
                'guru_staf_id'    => $kepsekOtomatis->guru_staf_id ?? ($validated['guru_staf_id'] ?? $profil->guru_staf_id),
            ]);

            $profil->id = 1;
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

            // Hapus file baru jika gagal simpan ke DB
            if (isset($validated['logo'])) {
                @unlink($targetPath . '/' . $validated['logo']);
            }
            if (isset($validated['logo_provinsi'])) {
                @unlink($targetPath . '/' . $validated['logo_provinsi']);
            }

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
                $targetPath = public_path('uploads/profil');
                
                // Hapus Logo Sekolah
                if ($profil->logo) {
                    $filePath = $targetPath . '/' . str_replace('uploads/profil/', '', $profil->logo);
                    if (file_exists($filePath)) @unlink($filePath);
                }

                // Hapus Logo Provinsi
                if ($profil->logo_provinsi) {
                    $fileProvPath = $targetPath . '/' . str_replace('uploads/profil/', '', $profil->logo_provinsi);
                    if (file_exists($fileProvPath)) @unlink($fileProvPath);
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