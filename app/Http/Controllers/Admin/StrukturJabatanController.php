<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StrukturJabatan;
use App\Http\Resources\StrukturJabatanResource;
use App\Http\Requests\StoreStrukturJabatanRequest;
use App\Http\Requests\UpdateStrukturJabatanRequest;
use Illuminate\Http\JsonResponse;
use Throwable;

class StrukturJabatanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $data = StrukturJabatan::with('guru')->orderBy('urutan_tampil')->get();
        return response()->json(StrukturJabatanResource::collection($data));
    }
    
    public function show(StrukturJabatan $strukturJabatan): JsonResponse
    {
        return response()->json(new StrukturJabatanResource($strukturJabatan->load('guru')));
    }

    public function store(StoreStrukturJabatanRequest $request): JsonResponse
    {
        try {
            $item = StrukturJabatan::create($request->validated());
            return response()->json(new StrukturJabatanResource($item->load('guru')), 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat struktur jabatan'
            ], 500);
        }
    }

    public function update(UpdateStrukturJabatanRequest $request, StrukturJabatan $strukturJabatan): JsonResponse
    {
        try {
            $strukturJabatan->update($request->validated());
            return response()->json(new StrukturJabatanResource($strukturJabatan->load('guru')));
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui struktur jabatan'
            ], 500);
        }
    }

    public function destroy(StrukturJabatan $strukturJabatan): JsonResponse
    {
        try {
            $strukturJabatan->delete();
            return response()->json(null, 204);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus struktur jabatan'
            ], 500);
        }
    }
}