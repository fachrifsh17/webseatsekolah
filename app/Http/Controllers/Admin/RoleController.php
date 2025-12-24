<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Http\Resources\RoleResource;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $roles = Role::all();
        return new JsonResponse(RoleResource::collection($roles));
    }
    
    public function show(Role $role): JsonResponse
    {
        return new JsonResponse(new RoleResource($role));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = Role::create($request->validated());
        return new JsonResponse(new RoleResource($role), 201);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $role->update($request->validated());
        return new JsonResponse(new RoleResource($role));
    }

    public function destroy(Role $role): JsonResponse
    {   
        // Proteksi hard-coded untuk role krusial sistem
        if (in_array($role->nama_role, ['Admin'])) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Role sistem tidak dapat dihapus.'
            ], 403);
        }

        $role->delete();
        return new JsonResponse(null, 204);
    }
}