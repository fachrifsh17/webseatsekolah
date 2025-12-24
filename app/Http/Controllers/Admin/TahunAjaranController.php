<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TahunAjaran;
use App\Http\Requests\StoreTahunAjaranRequest;
use App\Http\Requests\UpdateTahunAjaranRequest;
use App\Http\Resources\TahunAjaranResource;
use Illuminate\Http\JsonResponse;

class TahunAjaranController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $tahunAjaran = TahunAjaran::orderBy('nama', 'desc')->get();
        return new JsonResponse(TahunAjaranResource::collection($tahunAjaran));
    }

    public function store(StoreTahunAjaranRequest $request): JsonResponse
    {
        if ($request->aktif) {
            TahunAjaran::where('aktif', true)->update(['aktif' => false]);
        }

        $tahunAjaran = TahunAjaran::create($request->validated());

        return new JsonResponse([
            'success' => true,
            'message' => 'Tahun ajaran berhasil ditambahkan.',
            'data'    => new TahunAjaranResource($tahunAjaran)
        ], 201);
    }

    public function show(TahunAjaran $tahunAjaran): JsonResponse
    {
        return new JsonResponse(new TahunAjaranResource($tahunAjaran));
    }

    public function update(UpdateTahunAjaranRequest $request, TahunAjaran $tahunAjaran): JsonResponse
    {
        if ($request->aktif) {
            TahunAjaran::where('id', '!=', $tahunAjaran->id)
                ->where('aktif', true)
                ->update(['aktif' => false]);
        }

        $tahunAjaran->update($request->validated());

        return new JsonResponse([
            'success' => true,
            'message' => 'Tahun ajaran berhasil diperbarui.',
            'data'    => new TahunAjaranResource($tahunAjaran)
        ]);
    }

    public function destroy(TahunAjaran $tahunAjaran): JsonResponse
    {
        if ($tahunAjaran->kelas()->count() > 0) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Tidak dapat menghapus tahun ajaran yang masih memiliki data kelas.'
            ], 422);
        }

        $tahunAjaran->delete();

        return new JsonResponse([
            'success' => true,
            'message' => 'Tahun ajaran berhasil dihapus.'
        ]);
    }
}