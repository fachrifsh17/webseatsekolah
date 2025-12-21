<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Http\Requests\StoreKelasRequest;
use App\Http\Requests\UpdateKelasRequest;
use App\Http\Resources\KelasResource;
use Illuminate\Http\JsonResponse;

class KelasController extends Controller
{
    public function index(): JsonResponse
    {
        $kelas = Kelas::with(['jurusan', 'tahunAjaran'])->latest()->get();
        return response()->json(KelasResource::collection($kelas));
    }

    public function store(StoreKelasRequest $request): JsonResponse
    {
        $kelas = Kelas::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data kelas berhasil ditambahkan.',
            'data' => new KelasResource($kelas->load(['jurusan', 'tahunAjaran']))
        ], 201);
    }

    public function show(Kelas $kelas): JsonResponse
    {
        return response()->json(new KelasResource($kelas->load(['jurusan', 'tahunAjaran'])));
    }

    public function update(UpdateKelasRequest $request, Kelas $kelas): JsonResponse
    {
        $validated = $request->validated();

        $kelas->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data kelas berhasil diperbarui.',
            'data' => new KelasResource($kelas->load(['jurusan', 'tahunAjaran']))
        ]);
    }

    public function destroy(Kelas $kelas): JsonResponse
    {
        $kelas->delete();

        return response()->json(null, 204);
    }
}