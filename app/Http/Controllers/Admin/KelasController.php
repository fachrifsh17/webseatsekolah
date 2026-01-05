<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Http\Requests\StoreKelasRequest;
use App\Http\Requests\UpdateKelasRequest;
use App\Http\Resources\KelasResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class KelasController extends Controller
{
    public function index(): JsonResponse
    {
        $kelas = Kelas::with(['jurusan', 'tahunAjaran', 'waliKelas'])->latest()->get();
        return response()->json(KelasResource::collection($kelas));
    }

    public function store(StoreKelasRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Jika ada wali_kelas_id, cek conflict pada tahun ajaran yang sama
        if (!empty($validated['wali_kelas_id']) && !empty($validated['tahun_ajaran_id'])) {
            $exists = Kelas::where('wali_kelas_id', $validated['wali_kelas_id'])
                ->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conflict: wali kelas sudah ditugaskan pada kelas lain di tahun ajaran yang sama.'
                ], 409);
            }
        }

        DB::beginTransaction();
        try {
            $kelas = Kelas::create($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data kelas berhasil ditambahkan.',
                'data' => new KelasResource($kelas->load(['jurusan', 'tahunAjaran', 'waliKelas']))
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan data kelas.'
            ], 500);
        }
    }

    public function show(Kelas $kelas): JsonResponse
    {
        return response()->json(new KelasResource($kelas->load(['jurusan', 'tahunAjaran', 'waliKelas'])));
    }

    public function update(UpdateKelasRequest $request, Kelas $kelas): JsonResponse
    {
        $validated = $request->validated();

        // Jika wali_kelas_id atau tahun_ajaran_id diubah, cek conflict
        $waliId = $validated['wali_kelas_id'] ?? $kelas->wali_kelas_id;
        $tahunAjaranId = $validated['tahun_ajaran_id'] ?? $kelas->tahun_ajaran_id;

        if (!empty($waliId) && !empty($tahunAjaranId)) {
            $exists = Kelas::where('wali_kelas_id', $waliId)
                ->where('tahun_ajaran_id', $tahunAjaranId)
                ->where('id', '!=', $kelas->id) // kecualikan kelas saat ini
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conflict: wali kelas sudah ditugaskan pada kelas lain di tahun ajaran yang sama.'
                ], 409);
            }
        }

        DB::beginTransaction();
        try {
            $kelas->update($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data kelas berhasil diperbarui.',
                'data' => new KelasResource($kelas->load(['jurusan', 'tahunAjaran', 'waliKelas']))
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data kelas.'
            ], 500);
        }
    }

    public function destroy(Kelas $kelas): JsonResponse
    {
        DB::beginTransaction();
        try {
            $kelas->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data kelas berhasil dihapus.'
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data kelas.'
            ], 500);
        }
    }
}
