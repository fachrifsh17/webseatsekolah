<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Http\Resources\KelasResource;
use App\Http\Requests\StoreKelasRequest;
use Illuminate\Http\Request;

class KelasApiController extends Controller
{
    public function index()
    {
        $kelas = Kelas::with(['jurusan', 'tahunAjaran'])->latest()->get();
        return KelasResource::collection($kelas);
    }

    public function store(StoreKelasRequest $request)
    {
        $kelas = Kelas::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data kelas berhasil disimpan',
            'data' => new KelasResource($kelas->load(['jurusan', 'tahunAjaran']))
        ], 201);
    }

    public function show(Kelas $kela)
    {
        return new KelasResource($kela->load(['jurusan', 'tahunAjaran']));
    }

    public function update(Request $request, Kelas $kela)
    {
        $validated = $request->validate([
            'nama_kelas' => 'sometimes|required|string|max:50',
            'jurusan_id' => 'sometimes|required|exists:jurusan,id',
            'tahun_ajaran_id' => 'sometimes|required|exists:tahun_ajaran,id'
        ]);

        $kela->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data kelas berhasil diperbarui',
            'data' => new KelasResource($kela->load(['jurusan', 'tahunAjaran']))
        ]);
    }

    public function destroy(Kelas $kela)
    {
        $kela->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data kelas berhasil dihapus'
        ]);
    }
}