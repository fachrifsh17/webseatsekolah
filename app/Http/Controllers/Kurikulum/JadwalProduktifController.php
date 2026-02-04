<?php

namespace App\Http\Controllers\Kurikulum;

use App\Http\Controllers\Controller;
use App\Models\JadwalProduktif;
use App\Http\Requests\StoreJadwalProduktifRequest;
use App\Http\Requests\UpdateJadwalProduktifRequest;
use App\Http\Resources\JadwalProduktifResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class JadwalProduktifController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $perPage = min((int) request()->get('per_page', 20), 100);

        $jadwal = JadwalProduktif::with(['jurusan', 'guruStaf'])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => JadwalProduktifResource::collection($jadwal),
            'meta'    => [
                'current_page' => $jadwal->currentPage(),
                'last_page'    => $jadwal->lastPage(),
                'per_page'     => $jadwal->perPage(),
                'total'        => $jadwal->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function show(JadwalProduktif $jadwalProduktif): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new JadwalProduktifResource($jadwalProduktif->load(['jurusan', 'guruStaf']))
        ], Response::HTTP_OK);
    }

    public function store(StoreJadwalProduktifRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Cek duplikasi jadwal untuk jurusan yang dipilih
        if (JadwalProduktif::where('jurusan_id', $validated['jurusan_id'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal: Jurusan ini sudah memiliki jadwal produktif.'
            ], Response::HTTP_CONFLICT);
        }

        if ($request->hasFile('file_jadwal_path')) {
            $validated['file_jadwal_path'] = $request->file('file_jadwal_path')->store('jadwal_produktif', 'public');
        }

        try {
            $jadwal = JadwalProduktif::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Jadwal produktif berhasil ditambahkan oleh tim Kurikulum.',
                'data'    => new JadwalProduktifResource($jadwal->load(['jurusan', 'guruStaf']))
            ], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            if (!empty($validated['file_jadwal_path'])) {
                Storage::disk('public')->delete($validated['file_jadwal_path']);
            }
            Log::error('Kurikulum Store Jadwal Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan jadwal produktif.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateJadwalProduktifRequest $request, JadwalProduktif $jadwalProduktif): JsonResponse
    {
        $validated = $request->validated();

        // Jika mengubah jurusan, pastikan jurusan baru belum punya jadwal
        if (isset($validated['jurusan_id']) && $validated['jurusan_id'] != $jadwalProduktif->jurusan_id) {
            if (JadwalProduktif::where('jurusan_id', $validated['jurusan_id'])->where('id', '!=', $jadwalProduktif->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Konflik: Jurusan tujuan sudah memiliki jadwal produktif.'
                ], Response::HTTP_CONFLICT);
            }
        }

        if ($request->hasFile('file_jadwal_path')) {
            $newPath = $request->file('file_jadwal_path')->store('jadwal_produktif', 'public');
            if (!empty($jadwalProduktif->file_jadwal_path)) {
                Storage::disk('public')->delete($jadwalProduktif->file_jadwal_path);
            }
            $validated['file_jadwal_path'] = $newPath;
        }

        try {
            $jadwalProduktif->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Jadwal produktif berhasil diperbarui oleh tim Kurikulum.',
                'data'    => new JadwalProduktifResource($jadwalProduktif->load(['jurusan', 'guruStaf']))
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Kurikulum Update Jadwal Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui jadwal produktif.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(JadwalProduktif $jadwalProduktif): JsonResponse
    {
        try {
            if (!empty($jadwalProduktif->file_jadwal_path)) {
                Storage::disk('public')->delete($jadwalProduktif->file_jadwal_path);
            }

            $jadwalProduktif->delete();

            return response()->json([
                'success'      => true,
                'message'      => 'Jadwal produktif berhasil dihapus oleh tim Kurikulum.',
                'notification' => 'Berhasil dihapus'
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Kurikulum Delete Jadwal Error: ' . $e->getMessage());
            return response()->json([
                'success'   => false,
                'message'   => 'Gagal menghapus jadwal produktif.',
                'errors'    => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}