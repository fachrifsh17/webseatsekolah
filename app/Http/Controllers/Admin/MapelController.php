<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MataPelajaran;
use App\Http\Resources\MapelResource;
use App\Http\Requests\StoreMapelRequest;
use App\Http\Requests\UpdateMapelRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class MapelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        try {
            $data = MataPelajaran::with('jurusan')->paginate(12);

            return response()->json([
                'success' => true,
                'data'    => MapelResource::collection($data),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'per_page'     => $data->perPage(),
                    'total'        => $data->total(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch mata pelajaran', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar mata pelajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(?MataPelajaran $mapel): JsonResponse
    {
        if (!$mapel) {
            return response()->json([
                'success' => false,
                'message' => 'Data mata pelajaran tidak ditemukan',
                'errors'  => ['id' => ['Mata pelajaran dengan ID tersebut tidak ada']]
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            return response()->json([
                'success' => true,
                'data'    => new MapelResource($mapel->load('jurusan'))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch mata pelajaran detail', ['mapel_id' => (string) $mapel->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail mata pelajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreMapelRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (MataPelajaran::where('nama_mapel', $validated['nama_mapel'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Nama mata pelajaran sudah terdaftar.',
                'errors'  => ['nama_mapel' => ['Nama mata pelajaran sudah terdaftar.']]
            ], Response::HTTP_CONFLICT);
        }

        try {
            $item = DB::transaction(fn() => MataPelajaran::create($validated));

            return response()->json([
                'success' => true,
                'message' => 'Data mata pelajaran berhasil ditambahkan.',
                'data'    => new MapelResource($item->load('jurusan'))
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Failed to create mata pelajaran', ['payload' => $validated, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan mata pelajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateMapelRequest $request, ?MataPelajaran $mapel): JsonResponse
    {
        if (!$mapel) {
            return response()->json([
                'success' => false,
                'message' => 'Data mata pelajaran tidak ditemukan',
                'errors'  => ['id' => ['Mata pelajaran dengan ID tersebut tidak ada']]
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();

        if (!empty($validated['nama_mapel']) &&
            MataPelajaran::where('nama_mapel', $validated['nama_mapel'])
                ->where('id','<>',(string) $mapel->id)
                ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Nama mata pelajaran sudah terdaftar.',
                'errors'  => ['nama_mapel' => ['Nama mata pelajaran sudah terdaftar.']]
            ], Response::HTTP_CONFLICT);
        }

        try {
            DB::transaction(fn() => $mapel->update($validated));
            $mapel->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Data mata pelajaran berhasil diperbarui.',
                'data'    => new MapelResource($mapel->load('jurusan'))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to update mata pelajaran', ['mapel_id' => (string) $mapel->id, 'payload' => $validated, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui mata pelajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(?MataPelajaran $mapel): JsonResponse
    {
        if (!$mapel) {
            return response()->json([
                'success' => false,
                'message' => 'Data mata pelajaran tidak ditemukan',
                'errors'  => ['id' => ['Mata pelajaran dengan ID tersebut tidak ada']]
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            DB::transaction(fn() => $mapel->delete());

            return response()->json([
                'success'      => true,
                'message'      => 'Data mata pelajaran berhasil dihapus',
                'notification' => 'Berhasil dihapus'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to delete mata pelajaran', ['mapel_id' => (string) $mapel->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus mata pelajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
