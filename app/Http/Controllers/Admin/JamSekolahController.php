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
        return response()->json(JamSekolahResource::collection($jam));
    }

    public function store(StoreJamSekolahRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('file_jam')) {
            $validated['file_path'] = $request->file('file_jam')->store('jam_sekolah', 'public');
        }

        $jam = JamSekolah::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jadwal jam sekolah berhasil ditambahkan.',
            'data'    => new JamSekolahResource($jam->load('tahunAjaran'))
        ], 201);
    }

    public function show(JamSekolah $jamSekolah): JsonResponse
    {
        return response()->json(new JamSekolahResource($jamSekolah->load('tahunAjaran')));
    }

    public function update(UpdateJamSekolahRequest $request, JamSekolah $jamSekolah): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('file_jam')) {
            if ($jamSekolah->file_path) {
                Storage::disk('public')->delete($jamSekolah->file_path);
            }
            $validated['file_path'] = $request->file('file_jam')->store('jam_sekolah', 'public');
        }

        $jamSekolah->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jadwal jam sekolah berhasil diperbarui.',
            'data'    => new JamSekolahResource($jamSekolah->load('tahunAjaran'))
        ]);
    }

    public function destroy(JamSekolah $jamSekolah): JsonResponse
    {
        if ($jamSekolah->file_path) {
            Storage::disk('public')->delete($jamSekolah->file_path);
        }

        $jamSekolah->delete();

        return response()->json(null, 204);
    }
}