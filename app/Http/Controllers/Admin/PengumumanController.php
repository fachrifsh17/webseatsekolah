<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pengumuman;
use App\Http\Resources\PengumumanResource;
use App\Http\Requests\StorePengumumanRequest;
use App\Http\Requests\UpdatePengumumanRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class PengumumanController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);
        $this->authorizeResource(Pengumuman::class, 'pengumuman');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = min((int) $request->get('per_page', 10), 100);
            $query = Pengumuman::query();

            if ($request->filled('tanggal')) {
                $query->whereDate('tanggal_publikasi', $request->tanggal);
            }

            if ($request->has('penting')) {
                $query->where('penting', $request->boolean('penting'));
            }

            $data = $query->latest('tanggal_publikasi')->paginate($perPage);
            
            // Konversi ke array untuk mengambil metadata pagination tambahan
            $paginationData = $data->toArray();

            return response()->json([
                'success' => true,
                'data'    => PengumumanResource::collection($data),
                'meta'    => [
                    'current_page'  => $data->currentPage(),
                    'last_page'     => $data->lastPage(),
                    'per_page'      => $data->perPage(),
                    'total'         => $data->total(),
                    'from'          => $data->firstItem(),
                    'to'            => $data->lastItem(),
                    'next_page_url' => $data->nextPageUrl(),
                    'prev_page_url' => $data->previousPageUrl(),
                    'path'          => $paginationData['path'],
                    'links'         => $paginationData['links'],
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
        return response()->json([
            'success' => true,
            'data'    => new PengumumanResource($pengumuman),
        ], Response::HTTP_OK);
    }

    public function store(StorePengumumanRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Paksa tanggal mengikuti hari ini, abaikan input dari user
        $validated['tanggal_publikasi'] = now()->format('Y-m-d');

        if (Pengumuman::where('judul', $validated['judul'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Judul pengumuman sudah ada.',
            ], Response::HTTP_CONFLICT);
        }

        DB::beginTransaction();
        try {
            $item = Pengumuman::create($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pengumuman berhasil ditambahkan.',
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

        // Paksa tanggal update tetap mengikuti tanggal hari ini
        $validated['tanggal_publikasi'] = now()->format('Y-m-d');

        if (isset($validated['judul'])) {
            $conflict = Pengumuman::where('judul', $validated['judul'])
                ->where('id', '!=', $pengumuman->id)
                ->exists();
            
            if ($conflict) {
                return response()->json([
                    'success' => false,
                    'message' => 'Judul sudah digunakan oleh pengumuman lain.',
                ], Response::HTTP_CONFLICT);
            }
        }

        DB::beginTransaction();
        try {
            $pengumuman->update($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pengumuman berhasil diperbarui.',
                'data'    => new PengumumanResource($pengumuman),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Humas: Failed to update pengumuman', [
                'pengumuman_id' => $pengumuman->id,
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
                'success' => true,
                'message' => 'Pengumuman berhasil dihapus',
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Humas: Failed to delete pengumuman', [
                'pengumuman_id' => $pengumuman->id,
                'error'         => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus pengumuman',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}