<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JamSekolah;
use App\Http\Requests\StoreJamSekolahRequest;
use App\Http\Requests\UpdateJamSekolahRequest;
use App\Http\Resources\JamSekolahResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Throwable;

class JamSekolahController extends Controller
{
    public function index(): JsonResponse
    {
        $jam = JamSekolah::with('tahunAjaran')->latest()->get();
        return new JsonResponse(JamSekolahResource::collection($jam));
    }

    public function store(StoreJamSekolahRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            if ($request->hasFile('file_jam')) {
                $validated['file_path'] = $request->file('file_jam')
                    ->store('jam_sekolah', 'public');
            }

            $jam = JamSekolah::create($validated);

            return new JsonResponse([
                'success' => true,
                'message' => 'Jadwal jam sekolah berhasil ditambahkan.',
                'data'    => new JamSekolahResource($jam->load('tahunAjaran'))
            ], 201);
        } catch (Throwable $e) {
            if (!empty($validated['file_path'] ?? null)) {
                Storage::disk('public')->delete($validated['file_path']);
            }
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal menambahkan jadwal jam sekolah.'
            ], 500);
        }
    }

    public function show(JamSekolah $jamSekolah): JsonResponse
    {
        return new JsonResponse(
            new JamSekolahResource($jamSekolah->load('tahunAjaran'))
        );
    }

    public function update(UpdateJamSekolahRequest $request, JamSekolah $jamSekolah): JsonResponse
    {
        $validated = $request->validated();

        try {
            if ($request->hasFile('file_jam')) {
                if ($jamSekolah->file_path) {
                    Storage::disk('public')->delete($jamSekolah->file_path);
                }
                $validated['file_path'] = $request->file('file_jam')
                    ->store('jam_sekolah', 'public');
            }

            $jamSekolah->update($validated);

            return new JsonResponse([
                'success' => true,
                'message' => 'Jadwal jam sekolah berhasil diperbarui.',
                'data'    => new JamSekolahResource($jamSekolah->load('tahunAjaran'))
            ]);
        } catch (Throwable $e) {
            if (!empty($validated['file_path'] ?? null)) {
                Storage::disk('public')->delete($validated['file_path']);
            }
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal memperbarui jadwal jam sekolah.'
            ], 500);
        }
    }

    public function destroy(JamSekolah $jamSekolah): JsonResponse
    {
        try {
            if ($jamSekolah->file_path) {
                Storage::disk('public')->delete($jamSekolah->file_path);
            }

            $jamSekolah->delete();

            return new JsonResponse(null, 204);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal menghapus jadwal jam sekolah.'
            ], 500);
        }
    }
}