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
        return response()->json(JadwalProduktifResource::collection($jadwal));
    }

    public function store(StoreJadwalProduktifRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('file_jadwal')) {
            $validated['file_jadwal_path'] = $request->file('file_jadwal')->store('jadwal_produktif', 'public');
        }

        $jadwal = JadwalProduktif::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jadwal produktif berhasil ditambahkan.',
            'data' => new JadwalProduktifResource($jadwal->load(['jurusan', 'guruStaf']))
        ], 201);
    }

    public function show(JadwalProduktif $jadwalProduktif): JsonResponse
    {
        return response()->json(new JadwalProduktifResource($jadwalProduktif->load(['jurusan', 'guruStaf'])));
    }

    public function update(UpdateJadwalProduktifRequest $request, JadwalProduktif $jadwalProduktif): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('file_jadwal')) {
            if ($jadwalProduktif->file_jadwal_path) {
                Storage::disk('public')->delete($jadwalProduktif->file_jadwal_path);
            }
            $validated['file_jadwal_path'] = $request->file('file_jadwal')->store('jadwal_produktif', 'public');
        }

        $jadwalProduktif->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jadwal produktif berhasil diperbarui.',
            'data' => new JadwalProduktifResource($jadwalProduktif->load(['jurusan', 'guruStaf']))
        ]);
    }

    public function destroy(JadwalProduktif $jadwalProduktif): JsonResponse
    {
        if ($jadwalProduktif->file_jadwal_path) {
            Storage::disk('public')->delete($jadwalProduktif->file_jadwal_path);
        }

        $jadwalProduktif->delete();

        return response()->json(null, 204);
    }
}