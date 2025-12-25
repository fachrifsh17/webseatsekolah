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
        $this->middleware('role:Admin,Guru');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $data = PortalSosmed::paginate(12);
        return new JsonResponse(PortalSosmedResource::collection($data));
    }
    
    public function show(PortalSosmed $portal): JsonResponse
    {
        return new JsonResponse(new PortalSosmedResource($portal));
    }

    public function store(StorePortalRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $item = DB::transaction(function () use ($validated) {
            return PortalSosmed::create($validated);
        });

        return new JsonResponse(new PortalSosmedResource($item), 201);
    }

    public function update(UpdatePortalRequest $request, PortalSosmed $portal): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($portal, $validated) {
            $portal->update($validated);
        });

        return new JsonResponse(new PortalSosmedResource($portal));
    }

    public function destroy(PortalSosmed $portal): JsonResponse
    {
        DB::transaction(function () use ($portal) {
            $portal->delete();
        });

        return new JsonResponse(null, 204);
    }
}