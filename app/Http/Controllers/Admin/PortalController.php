<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PortalSosmed;
use App\Http\Resources\PortalSosmedResource;
use App\Http\Requests\StorePortalRequest;
use App\Http\Requests\UpdatePortalRequest;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Http\JsonResponse;

class PortalController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth.token'),
            new Middleware('role:Admin,SuperAdmin'),
            new Middleware('log.admin', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index()
    {
        $data = PortalSosmed::paginate(12);
        return PortalSosmedResource::collection($data);
    }
    
    public function show(PortalSosmed $portal)
    {
        return new PortalSosmedResource($portal);
    }

    public function store(StorePortalRequest $request)
    {
        $item = PortalSosmed::create($request->validated()); 
        
        return new PortalSosmedResource($item);
    }

    public function update(UpdatePortalRequest $request, PortalSosmed $portal) 
    {
        $portal->update($request->validated()); 
        
        return new PortalSosmedResource($portal);
    }

    public function destroy(PortalSosmed $portal): JsonResponse
    {
        $portal->delete(); 
        
        return response()->json(null, 204);
    }
}