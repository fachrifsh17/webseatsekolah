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
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): AnonymousResourceCollection
    {
        $data = MataPelajaran::with('jurusan')->paginate(12);
        return MapelResource::collection($data);
    }

    public function show(?MataPelajaran $mapel): JsonResponse
    {
        if (!$mapel) {
            return response()->json([
                'success' => false,
                'message' => 'Data mata pelajaran tidak ditemukan',
                'errors'  => ['id' => ['Mata pelajaran dengan ID tersebut tidak ada']]
            ], 404);
        }

        return response()->json(new MapelResource($mapel->load('jurusan')));
    }

    public function store(StoreMapelRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (MataPelajaran::where('nama_mapel', $validated['nama_mapel'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Nama mata pelajaran sudah terdaftar.',
                'errors'  => ['nama_mapel' => ['Nama mata pelajaran sudah terdaftar.']]
            ], 409);
        }

        try {
            $item = DB::transaction(fn() => MataPelajaran::create($validated));
            return response()->json([
                'success' => true,
                'message' => 'Data mata pelajaran berhasil ditambahkan.',
                'data'    => new MapelResource($item->load('jurusan'))
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan mata pelajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], 500);
        }
    }

    public function update(UpdateMapelRequest $request, ?MataPelajaran $mapel): JsonResponse
    {
        if (!$mapel) {
            return response()->json([
                'success' => false,
                'message' => 'Data mata pelajaran tidak ditemukan',
                'errors'  => ['id' => ['Mata pelajaran dengan ID tersebut tidak ada']]
            ], 404);
        }

        $validated = $request->validated();

        if (!empty($validated['nama_mapel']) &&
            MataPelajaran::where('nama_mapel', $validated['nama_mapel'])
                ->where('id','<>',$mapel->id)
                ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Nama mata pelajaran sudah terdaftar.',
                'errors'  => ['nama_mapel' => ['Nama mata pelajaran sudah terdaftar.']]
            ], 409);
        }

        try {
            DB::transaction(fn() => $mapel->update($validated));
            $mapel->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Data mata pelajaran berhasil diperbarui.',
                'data'    => new MapelResource($mapel->load('jurusan'))
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui mata pelajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], 500);
        }
    }

    public function destroy(?MataPelajaran $mapel): JsonResponse
    {
        if (!$mapel) {
            return response()->json([
                'success' => false,
                'message' => 'Data mata pelajaran tidak ditemukan',
                'errors'  => ['id' => ['Mata pelajaran dengan ID tersebut tidak ada']]
            ], 404);
        }

        try {
            DB::transaction(fn() => $mapel->delete());

            return response()->json([
                'success'      => true,
                'message'      => 'Data mata pelajaran berhasil dihapus',
                'notification' => 'Berhasil dihapus'
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus mata pelajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], 500);
        }
    }
}
