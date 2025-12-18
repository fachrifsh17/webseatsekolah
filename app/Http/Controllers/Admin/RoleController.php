<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Http\Resources\RoleResource;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class RoleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth.token'),
            // Manajemen Role biasanya dibatasi hanya untuk SuperAdmin
            new Middleware('role:SuperAdmin'),
            new Middleware('log.admin', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index()
    {
        $roles = Role::all();
        return RoleResource::collection($roles);
    }
    
    public function show(Role $role)
    {
        return new RoleResource($role);
    }

    public function store(StoreRoleRequest $request)
    {
        $role = Role::create($request->validated());
        return new RoleResource($role);
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        $role->update($request->validated());
        return new RoleResource($role);
    }

    public function destroy(Role $role): JsonResponse
    {   
        // Proteksi Hard-coded untuk role krusial sistem
        if (in_array($role->nama_role, ['SuperAdmin', 'Admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Role sistem tidak dapat dihapus.'
            ], 403);
        }

        $role->delete();
        return response()->json(null, 204);
    }
}