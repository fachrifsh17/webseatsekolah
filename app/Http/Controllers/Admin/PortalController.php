<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PortalSosmed;
use App\Http\Resources\PortalSosmedResource;
use App\Http\Requests\StorePortalRequest;
use App\Http\Requests\UpdatePortalRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class PortalController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin'); 
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $data = PortalSosmed::paginate(12);

        return response()->json([
            'success' => true,
            'data' => PortalSosmedResource::collection($data),
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'per_page'     => $data->perPage(),
                'total'        => $data->total(),
            ]
        ]);
    }
    
    public function show(PortalSosmed $portal): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new PortalSosmedResource($portal),
        ]);
    }

    public function store(StorePortalRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (isset($validated['is_active'])) {
            $validated['is_active'] = (int) $validated['is_active'];
        }

        if (!empty($validated['url_link'])) {
            $validated['url_link'] = rtrim($validated['url_link'], '/');
        }

        $item = DB::transaction(function () use ($validated) {
            return PortalSosmed::create($validated);
        });

        return response()->json([
            'success' => true,
            'data' => new PortalSosmedResource($item->fresh()),
        ], 201);
    }

    public function update(UpdatePortalRequest $request, PortalSosmed $portal): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($portal, $validated) {
            $portal->update($validated);
        });

        return response()->json([
            'success' => true,
            'data' => new PortalSosmedResource($portal->fresh()),
        ]);
    }

    public function destroy(PortalSosmed $portal): JsonResponse
    {
        DB::transaction(function () use ($portal) {
            $portal->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Portal berhasil dihapus',
        ], 200);
    }
}
