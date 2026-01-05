<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JamSekolah;
use App\Http\Resources\JamSekolahResource;
use App\Http\Requests\UpdateJamSekolahRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Throwable;

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

        $fileKey = $request->hasFile('file_path') ? 'file_path' : ($request->hasFile('file') ? 'file' : null);
        if ($fileKey) {
            $newPath = $request->file($fileKey)->store('jam_sekolah', 'public');
            if ($newPath) {
                $validated['file_path'] = $newPath;
            }
        }

        if (empty($validated)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data untuk diperbarui.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            if ($jamSekolah === null) {
                $jamSekolah = JamSekolah::create($validated);
                $jamSekolah->refresh()->load('tahunAjaran');
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Jadwal jam sekolah berhasil dibuat.',
                    'data'    => new JamSekolahResource($jamSekolah)
                ], 201);
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
            ], 200);
        } catch (Throwable $e) {
            DB::rollBack();

            if (!empty($validated['file_path']) && ($jamSekolah?->file_path ?? null) !== $validated['file_path']) {
                Storage::disk('public')->delete($validated['file_path']);
            }

            Log::error('JamSekolah upsert error: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses jadwal jam sekolah.'
            ], 500);
        }
    }
}
