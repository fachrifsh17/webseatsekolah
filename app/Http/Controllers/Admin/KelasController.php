<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Http\Requests\StoreKelasRequest;
use App\Http\Requests\UpdateKelasRequest;
use App\Http\Resources\KelasResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class KelasController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $kelas = Kelas::with(['jurusan', 'tahunAjaran', 'waliKelas'])
                ->latest()
                ->get();

            return response()->json([
                'success' => true,
                'data'    => KelasResource::collection($kelas),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch kelas', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar kelas.',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreKelasRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $tahunAktif = TahunAjaran::where('is_active', true)->first();
        if (!$tahunAktif) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal: Tidak ada Tahun Ajaran yang aktif.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $validated['tahun_ajaran_id'] = $tahunAktif->id;

        // Cek conflict wali kelas
        if (!empty($validated['wali_kelas_id'])) {
            $existsWali = Kelas::where('wali_kelas_id', $validated['wali_kelas_id'])
                ->where('tahun_ajaran_id', $tahunAktif->id)
                ->exists();

            if ($existsWali) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conflict: Guru tersebut sudah menjadi wali kelas di tahun ajaran aktif.',
                ], Response::HTTP_CONFLICT);
            }
        }

        // Cek conflict nama_kelas
        if (!empty($validated['nama_kelas'])) {
            $existsNama = Kelas::where('nama_kelas', $validated['nama_kelas'])
                ->where('tahun_ajaran_id', $tahunAktif->id)
                ->exists();

            if ($existsNama) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conflict: Nama kelas sudah terdaftar di tahun ajaran aktif.',
                ], Response::HTTP_CONFLICT);
            }
        }

        try {
            $kelas = DB::transaction(function () use ($validated) {
                return Kelas::create($validated);
            });

            return response()->json([
                'success' => true,
                'message' => 'Data kelas berhasil ditambahkan.',
                'data'    => new KelasResource($kelas->load(['jurusan', 'tahunAjaran', 'waliKelas'])),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Failed to create kelas', ['payload' => $validated, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan data kelas.',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Kelas $kelas): JsonResponse
    {
        try {
            $kelas->load(['jurusan', 'tahunAjaran', 'waliKelas']);
            return response()->json([
                'success' => true,
                'data'    => new KelasResource($kelas),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch kelas detail', ['kelas_id' => $kelas->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail kelas.',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateKelasRequest $request, Kelas $kelas): JsonResponse
    {
        $validated = $request->validated();

        $tahunAktif = TahunAjaran::where('is_active', true)->first();
        if (!$tahunAktif) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal: Tidak ada Tahun Ajaran yang aktif.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $validated['tahun_ajaran_id'] = $tahunAktif->id;
        $waliId = $validated['wali_kelas_id'] ?? $kelas->wali_kelas_id;

        // Cek conflict wali kelas
        if (!empty($waliId)) {
            $existsWali = Kelas::where('wali_kelas_id', $waliId)
                ->where('tahun_ajaran_id', $tahunAktif->id)
                ->where('id', '!=', $kelas->id)
                ->exists();

            if ($existsWali) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conflict: Guru tersebut sudah menjadi wali kelas di tahun ajaran aktif.',
                ], Response::HTTP_CONFLICT);
            }
        }

        // Cek conflict nama_kelas
        if (!empty($validated['nama_kelas'])) {
            $existsNama = Kelas::where('nama_kelas', $validated['nama_kelas'])
                ->where('tahun_ajaran_id', $tahunAktif->id)
                ->where('id', '!=', $kelas->id)
                ->exists();

            if ($existsNama) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conflict: Nama kelas sudah terdaftar di tahun ajaran aktif.',
                ], Response::HTTP_CONFLICT);
            }
        }

        try {
            DB::transaction(function () use ($kelas, $validated) {
                $kelas->update($validated);
            });

            return response()->json([
                'success' => true,
                'message' => 'Data kelas berhasil diperbarui.',
                'data'    => new KelasResource($kelas->load(['jurusan', 'tahunAjaran', 'waliKelas'])),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to update kelas', ['kelas_id' => $kelas->id, 'payload' => $validated, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data kelas.',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Kelas $kelas): JsonResponse
    {
        try {
            DB::transaction(function () use ($kelas) {
                $kelas->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Data kelas berhasil dihapus.',
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to delete kelas', ['kelas_id' => $kelas->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data kelas.',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
