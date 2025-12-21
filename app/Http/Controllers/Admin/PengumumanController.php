<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pengumuman;
use App\Http\Resources\PengumumanResource;
use App\Http\Requests\StorePengumumanRequest;
use App\Http\Requests\UpdatePengumumanRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Throwable;

class PengumumanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin,Guru');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $data = Pengumuman::latest()->paginate(10);
        return response()->json(PengumumanResource::collection($data));
    }
    
    public function show(Pengumuman $pengumuman): JsonResponse
    {
        return response()->json(new PengumumanResource($pengumuman));
    }

    public function store(StorePengumumanRequest $request): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $item = Pengumuman::create($validated);
            DB::commit();
            return response()->json(new PengumumanResource($item), 201);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat pengumuman'
            ], 500);
        }
    }

    public function update(UpdatePengumumanRequest $request, Pengumuman $pengumuman): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $pengumuman->update($validated);
            DB::commit();
            return response()->json(new PengumumanResource($pengumuman));
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui pengumuman'
            ], 500);
        }
    }

    public function destroy(Pengumuman $pengumuman): JsonResponse
    {
        DB::beginTransaction();
        try {
            $pengumuman->delete();
            DB::commit();
            return response()->json(null, 204);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus pengumuman'
            ], 500);
        }
    }
}