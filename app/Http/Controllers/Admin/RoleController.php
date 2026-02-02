<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Http\Resources\RoleResource;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

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
        try {
            $roles = Role::orderBy('nama_role')->get();

            return response()->json([
                'success' => true,
                'data'    => RoleResource::collection($roles),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch roles', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar role.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    public function show(Role $role): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => new RoleResource($role),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch role', ['role_id' => (string)$role->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail role.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        try {
            $payload = $request->validated();
            $role = Role::create($payload);

            return response()->json([
                'success' => true,
                'message' => 'Role berhasil ditambahkan.',
                'data'    => new RoleResource($role),
            ], Response::HTTP_CREATED);
        } catch (QueryException $e) {
            Log::error('Failed to create role (QueryException)', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat role.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $e) {
            Log::error('Failed to create role', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat role.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        try {
            $role->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Role berhasil diperbarui.',
                'data'    => new RoleResource($role),
            ], Response::HTTP_OK);
        } catch (QueryException $e) {
            Log::error('Failed to update role (QueryException)', ['role_id' => (string)$role->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui role.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $e) {
            Log::error('Failed to update role', ['role_id' => (string)$role->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui role.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Role $role): JsonResponse
    {   
        if (in_array($role->nama_role, ['Admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Role sistem tidak dapat dihapus.'
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $role->delete();
            return response()->json([
                'success' => true,
                'message' => 'Role berhasil dihapus.'
            ], Response::HTTP_NO_CONTENT);
        } catch (Throwable $e) {
            Log::error('Failed to delete role', ['role_id' => (string)$role->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus role.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
