<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JamSekolah;
use App\Models\TahunAjaran;
use App\Http\Resources\JamSekolahResource;
use App\Http\Requests\UpdateJamSekolahRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class JamSekolahController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['update']);
    }

    public function update(UpdateJamSekolahRequest $request, ?JamSekolah $jamSekolah = null): JsonResponse
    {
        $validated = $request->validated();

        $tahunAktif = TahunAjaran::where('is_active', true)->first();

        if (!$tahunAktif) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal: Tidak ada Tahun Ajaran yang aktif (is_active = true).'
            ], Response::HTTP_BAD_REQUEST);
        }

        $validated['tahun_ajaran_id'] = $tahunAktif->id;

        $fileKey = $request->hasFile('file_path') ? 'file_path' : ($request->hasFile('file') ? 'file' : null);
        if ($fileKey) {
            $newPath = $request->file($fileKey)->store('jam_sekolah', 'public');
            if ($newPath) {
                $validated['file_path'] = $newPath;
            }
        }

        DB::beginTransaction();
        try {
            if ($jamSekolah === null) {
                $jamSekolah = JamSekolah::create($validated);
                $jamSekolah->refresh()->load('tahunAjaran');
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Jadwal jam sekolah berhasil dibuat otomatis untuk tahun aktif.',
                    'data'    => new JamSekolahResource($jamSekolah)
                ], Response::HTTP_CREATED);
            }

            $oldPath = $jamSekolah->file_path;
            $jamSekolah->update($validated);
            $jamSekolah->refresh()->load('tahunAjaran');
            DB::commit();

            if (!empty($validated['file_path']) && $oldPath && $validated['file_path'] !== $oldPath) {
                Storage::disk('public')->delete($oldPath);
            }

            return response()->json([
                'success' => true,
                'message' => 'Jadwal jam sekolah berhasil diperbarui.',
                'data'    => new JamSekolahResource($jamSekolah)
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();

            if (!empty($validated['file_path']) && ($jamSekolah?->file_path ?? null) !== $validated['file_path']) {
                Storage::disk('public')->delete($validated['file_path']);
            }

            Log::error('JamSekolah upsert error', [
                'jam_sekolah_id' => $jamSekolah ? (string) $jamSekolah->id : null,
                'error'          => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses jadwal jam sekolah.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}