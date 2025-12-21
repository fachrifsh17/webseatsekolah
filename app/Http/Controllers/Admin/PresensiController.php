<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Presensi;
use App\Http\Requests\StorePresensiRequest;
use App\Http\Requests\UpdatePresensiRequest;
use App\Http\Resources\PresensiResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PresensiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Presensi::with(['siswa', 'tahunAjaran']);

        if ($request->has('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        }

        if ($request->has('kelas_id')) {
            $query->whereHas('siswa', function($q) use ($request) {
                $q->where('kelas_id', $request->kelas_id);
            });
        }

        return response()->json(PresensiResource::collection($query->latest()->paginate(50)));
    }

    public function store(StorePresensiRequest $request): JsonResponse
    {
        $presensi = Presensi::updateOrCreate(
            [
                'siswa_id' => $request->siswa_id,
                'tanggal'  => $request->tanggal,
            ],
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Data presensi berhasil disimpan.',
            'data'    => new PresensiResource($presensi->load('siswa'))
        ], 201);
    }

    public function show(Presensi $presensi): JsonResponse
    {
        return response()->json(new PresensiResource($presensi->load(['siswa', 'tahunAjaran'])));
    }

    public function update(UpdatePresensiRequest $request, Presensi $presensi): JsonResponse
    {
        $validated = $request->validated();

        $presensi->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data presensi berhasil diperbarui.',
            'data'    => new PresensiResource($presensi->load('siswa'))
        ]);
    }

    public function destroy(Presensi $presensi): JsonResponse
    {
        $presensi->delete();

        return response()->json(null, 204);
    }
}