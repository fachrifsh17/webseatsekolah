<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PoinSiswa;
use App\Http\Requests\StorePoinSiswaRequest;
use App\Http\Requests\UpdatePoinSiswaRequest;
use App\Http\Resources\PoinSiswaResource;
use Illuminate\Http\JsonResponse;

class PoinSiswaController extends Controller
{
    public function index(): JsonResponse
    {
        $poin = PoinSiswa::with(['siswa', 'guru', 'tahunAjaran'])->latest()->paginate(20);
        return response()->json(PoinSiswaResource::collection($poin));
    }

    public function store(StorePoinSiswaRequest $request): JsonResponse
    {
        $poin = PoinSiswa::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Poin siswa berhasil dicatat.',
            'data'    => new PoinSiswaResource($poin->load(['siswa', 'guru']))
        ], 201);
    }

    public function show(PoinSiswa $poinSiswa): JsonResponse
    {
        return response()->json(new PoinSiswaResource($poinSiswa->load(['siswa', 'guru', 'tahunAjaran'])));
    }

    public function update(UpdatePoinSiswaRequest $request, PoinSiswa $poinSiswa): JsonResponse
    {
        $validated = $request->validated();

        $poinSiswa->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Catatan poin berhasil diperbarui.',
            'data'    => new PoinSiswaResource($poinSiswa->load(['siswa', 'guru']))
        ]);
    }

    public function destroy(PoinSiswa $poinSiswa): JsonResponse
    {
        $poinSiswa->delete();

        return response()->json(null, 204);
    }
}