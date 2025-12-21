<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Http\Resources\RoleResource;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use Illuminate\Http\JsonResponse;
use Throwable;

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
        return response()->json(RoleResource::collection($roles));
    }
    
    public function show(Role $role): JsonResponse
    {
        return response()->json(new RoleResource($role));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        try {
            $role = Role::create($request->validated());
            return response()->json(new RoleResource($role), 201);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal membuat role'
            ], 500);
        }
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        try {
            $role->update($request->validated());
            return response()->json(new RoleResource($role));
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memperbarui role'
            ], 500);
        }
    }

    public function destroy(Role $role): JsonResponse
    {   
        // Proteksi hard-coded untuk role krusial sistem
        if (in_array($role->nama_role, ['Admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Role sistem tidak dapat dihapus.'
            ], 403);
        }

        $role->delete();
        return response()->json(null, 204);
    }
}