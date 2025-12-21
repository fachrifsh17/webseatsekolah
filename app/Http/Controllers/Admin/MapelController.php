<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MataPelajaran;
use App\Http\Resources\MapelResource;
use App\Http\Requests\StoreMapelRequest;
use App\Http\Requests\UpdateMapelRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Throwable;

class MapelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin,Guru');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): AnonymousResourceCollection
    {
        $data = MataPelajaran::with('jurusan')->paginate(12);
        return MapelResource::collection($data);
    }
    
    public function show(MataPelajaran $mapel): JsonResponse
    {
        return response()->json(new MapelResource($mapel->load('jurusan')));
    }

    public function store(StoreMapelRequest $request): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $item = MataPelajaran::create($validated);
            DB::commit();
            return response()->json(new MapelResource($item->load('jurusan')), 201);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat mata pelajaran'
            ], 500);
        }
    }

    public function update(UpdateMapelRequest $request, MataPelajaran $mapel): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $mapel->update($validated);
            DB::commit();
            return response()->json(new MapelResource($mapel->load('jurusan')));
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui mata pelajaran'
            ], 500);
        }
    }

    public function destroy(MataPelajaran $mapel): JsonResponse
    {
        DB::beginTransaction();
        try {
            $mapel->delete();
            DB::commit();

            return response()->json(null, 204);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus mata pelajaran'
            ], 500);
        }
    }
}