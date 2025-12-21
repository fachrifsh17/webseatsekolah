<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PortalSosmed;
use App\Http\Resources\PortalSosmedResource;
use App\Http\Requests\StorePortalRequest;
use App\Http\Requests\UpdatePortalRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Throwable;

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
        return response()->json(PortalSosmedResource::collection($data));
    }
    
    public function show(PortalSosmed $portal): JsonResponse
    {
        return response()->json(new PortalSosmedResource($portal));
    }

    public function store(StorePortalRequest $request): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $item = PortalSosmed::create($validated);
            DB::commit();
            return response()->json(new PortalSosmedResource($item), 201);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat portal'
            ], 500);
        }
    }

    public function update(UpdatePortalRequest $request, PortalSosmed $portal): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $portal->update($validated);
            DB::commit();
            return response()->json(new PortalSosmedResource($portal));
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui portal'
            ], 500);
        }
    }

    public function destroy(PortalSosmed $portal): JsonResponse
    {
        DB::beginTransaction();
        try {
            $portal->delete();
            DB::commit();
            return response()->json(null, 204);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus portal'
            ], 500);
        }
    }
}