<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JadwalProduktif;
use App\Http\Requests\StoreJadwalProduktifRequest;
use App\Http\Requests\UpdateJadwalProduktifRequest;
use App\Http\Resources\JadwalProduktifResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Throwable;

class JadwalProduktifController extends Controller
{
    public function index(): JsonResponse
    {
        $jadwal = JadwalProduktif::with(['jurusan', 'guruStaf'])->latest()->get();
        return new JsonResponse(JadwalProduktifResource::collection($jadwal));
    }

    public function store(StoreJadwalProduktifRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            if ($request->hasFile('file_jadwal')) {
                $validated['file_jadwal_path'] = $request->file('file_jadwal')
                    ->store('jadwal_produktif', 'public');
            }

            $jadwal = JadwalProduktif::create($validated);

            return new JsonResponse([
                'success' => true,
                'message' => 'Jadwal produktif berhasil ditambahkan.',
                'data'    => new JadwalProduktifResource($jadwal->load(['jurusan', 'guruStaf']))
            ], 201);
        } catch (Throwable $e) {
            if (!empty($validated['file_jadwal_path'] ?? null)) {
                Storage::disk('public')->delete($validated['file_jadwal_path']);
            }
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal menambahkan jadwal produktif.'
            ], 500);
        }
    }

    public function show(JadwalProduktif $jadwalProduktif): JsonResponse
    {
        return new JsonResponse(
            new JadwalProduktifResource($jadwalProduktif->load(['jurusan', 'guruStaf']))
        );
    }

    public function update(UpdateJadwalProduktifRequest $request, JadwalProduktif $jadwalProduktif): JsonResponse
    {
        $validated = $request->validated();

        try {
            if ($request->hasFile('file_jadwal')) {
                if ($jadwalProduktif->file_jadwal_path) {
                    Storage::disk('public')->delete($jadwalProduktif->file_jadwal_path);
                }
                $validated['file_jadwal_path'] = $request->file('file_jadwal')
                    ->store('jadwal_produktif', 'public');
            }

            $jadwalProduktif->update($validated);

            return new JsonResponse([
                'success' => true,
                'message' => 'Jadwal produktif berhasil diperbarui.',
                'data'    => new JadwalProduktifResource($jadwalProduktif->load(['jurusan', 'guruStaf']))
            ]);
        } catch (Throwable $e) {
            if (!empty($validated['file_jadwal_path'] ?? null)) {
                Storage::disk('public')->delete($validated['file_jadwal_path']);
            }
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal memperbarui jadwal produktif.'
            ], 500);
        }
    }

    public function destroy(JadwalProduktif $jadwalProduktif): JsonResponse
    {
        try {
            if ($jadwalProduktif->file_jadwal_path) {
                Storage::disk('public')->delete($jadwalProduktif->file_jadwal_path);
            }

            $jadwalProduktif->delete();

            return new JsonResponse(null, 204);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal menghapus jadwal produktif.'
            ], 500);
        }
    }
}