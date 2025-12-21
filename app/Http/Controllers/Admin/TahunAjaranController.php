<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TahunAjaran;
use App\Http\Requests\StoreTahunAjaranRequest;
use App\Http\Requests\UpdateTahunAjaranRequest;
use App\Http\Resources\TahunAjaranResource;
use Illuminate\Http\JsonResponse;
use Throwable;

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
        return response()->json(TahunAjaranResource::collection($tahunAjaran));
    }

    public function store(StoreTahunAjaranRequest $request): JsonResponse
    {
        try {
            if ($request->aktif) {
                TahunAjaran::where('aktif', true)->update(['aktif' => false]);
            }

            $tahunAjaran = TahunAjaran::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Tahun ajaran berhasil ditambahkan.',
                'data'    => new TahunAjaranResource($tahunAjaran)
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan tahun ajaran'
            ], 500);
        }
    }

    public function show(TahunAjaran $tahunAjaran): JsonResponse
    {
        return response()->json(new TahunAjaranResource($tahunAjaran));
    }

    public function update(UpdateTahunAjaranRequest $request, TahunAjaran $tahunAjaran): JsonResponse
    {
        try {
            if ($request->aktif) {
                TahunAjaran::where('id', '!=', $tahunAjaran->id)
                    ->where('aktif', true)
                    ->update(['aktif' => false]);
            }

            $tahunAjaran->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Tahun ajaran berhasil diperbarui.',
                'data'    => new TahunAjaranResource($tahunAjaran)
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui tahun ajaran'
            ], 500);
        }
    }

    public function destroy(TahunAjaran $tahunAjaran): JsonResponse
    {
        try {
            if ($tahunAjaran->kelas()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat menghapus tahun ajaran yang masih memiliki data kelas.'
                ], 422);
            }

            $tahunAjaran->delete();

            return response()->json([
                'success' => true,
                'message' => 'Tahun ajaran berhasil dihapus.'
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus tahun ajaran'
            ], 500);
        }
    }
}