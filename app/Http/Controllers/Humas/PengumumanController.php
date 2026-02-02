<?php

namespace App\Http\Controllers\Humas;

use App\Http\Controllers\Controller;
use App\Models\Pengumuman;
use App\Http\Resources\PengumumanResource;
use App\Http\Requests\StorePengumumanRequest;
use App\Http\Requests\UpdatePengumumanRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class PengumumanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        try {
            $perPage = min((int) request()->get('per_page', 10), 100);
            $data    = Pengumuman::latest()->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => PengumumanResource::collection($data),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'per_page'     => $data->perPage(),
                    'total'        => $data->total(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Humas: Failed to fetch pengumuman list', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar pengumuman',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    public function show(Pengumuman $pengumuman): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => new PengumumanResource($pengumuman),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Humas: Failed to fetch pengumuman detail', [
                'pengumuman_id' => (string) $pengumuman->id,
                'error'         => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail pengumuman',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StorePengumumanRequest $request): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $item = Pengumuman::create($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pengumuman berhasil ditambahkan oleh Humas.',
                'data'    => new PengumumanResource($item),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Humas: Failed to create pengumuman', ['payload' => $validated, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan pengumuman',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdatePengumumanRequest $request, Pengumuman $pengumuman): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $pengumuman->update($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pengumuman berhasil diperbarui oleh Humas.',
                'data'    => new PengumumanResource($pengumuman),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Humas: Failed to update pengumuman', [
                'pengumuman_id' => (string) $pengumuman->id,
                'payload'       => $validated,
                'error'         => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui pengumuman',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Pengumuman $pengumuman): JsonResponse
    {
        DB::beginTransaction();
        try {
            $pengumuman->delete();
            DB::commit();

            return response()->json([
                'success'      => true,
                'message'      => 'Pengumuman berhasil dihapus oleh Humas',
                'notification' => 'Berhasil dihapus'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Humas: Failed to delete pengumuman', [
                'pengumuman_id' => (string) $pengumuman->id,
                'error'         => $e->getMessage()
            ]);
            return response()->json([
                'success'      => false,
                'message'      => 'Gagal menghapus pengumuman',
                'notification' => 'Gagal dihapus',
                'errors'       => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}