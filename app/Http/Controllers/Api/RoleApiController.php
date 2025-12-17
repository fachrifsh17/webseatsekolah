<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Role; // Asumsi Model bernama Role
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RoleApiController extends Controller
{
    public function index()
    {
        $roles = Role::latest()->paginate(10);
        return RoleResource::collection($roles);
    }
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'role_name' => 'required|string|max:100|unique:roles,role_name',
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $role = Role::create($validator->validated());

        return response()->json([
            'message' => 'Role berhasil ditambahkan.',
            'data' => new RoleResource($role)
        ], 201);
    }
    public function show(Role $role)
    {
        return new RoleResource($role);
    }
    public function update(Request $request, Role $role)
    {
        $validator = Validator::make($request->all(), [
            'role_name' => 'required|string|max:100|unique:roles,role_name,' . $role->id,
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $role->update($validator->validated());

        return response()->json([
            'message' => 'Role berhasil diperbarui.',
            'data' => new RoleResource($role)
        ]);
    }
    public function destroy(Role $role)
    {
        $role->delete();
        return response()->json(null, 204);
    }
}