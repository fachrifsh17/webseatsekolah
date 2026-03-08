<?php

namespace App\Http\Controllers\Humas;

use App\Http\Controllers\Controller;
use App\Models\PortalSosmed;
use App\Http\Resources\PortalSosmedResource;
use App\Http\Requests\StorePortalRequest;
use App\Http\Requests\UpdatePortalRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class PortalController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);
        
        // Aktifkan ini setelah file Policy dibuat
        $this->authorizeResource(PortalSosmed::class, 'portal');
    }

    public function index(): JsonResponse
    {
        try {
            $perPage = min((int) request()->get('per_page', 12), 100);
            $data    = PortalSosmed::latest()->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => PortalSosmedResource::collection($data),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'per_page'     => $data->perPage(),
                    'total'        => $data->total(),
                ]
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch portal sosmed list', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar portal sosmed',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    public function show(PortalSosmed $portal): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new PortalSosmedResource($portal),
        ], Response::HTTP_OK);
    }

    public function store(StorePortalRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Logika pembersihan URL tetap dipertahankan
        if (!empty($validated['url_link'])) {
            $validated['url_link'] = rtrim($validated['url_link'], '/');
        }

        DB::beginTransaction();
        try {
            $item = PortalSosmed::create($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Portal sosmed berhasil ditambahkan.',
                'data'    => new PortalSosmedResource($item->fresh()),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Failed to create portal sosmed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan portal sosmed',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdatePortalRequest $request, PortalSosmed $portal): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $portal->update($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Portal sosmed berhasil diperbarui.',
                'data'    => new PortalSosmedResource($portal->fresh()),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Failed to update portal sosmed', ['id' => $portal->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui portal sosmed',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(PortalSosmed $portal): JsonResponse
    {
        DB::beginTransaction();
        try {
            $portal->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Portal sosmed berhasil dihapus',
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Failed to delete portal sosmed', ['id' => $portal->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus portal sosmed',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}