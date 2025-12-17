<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;

class RoleController extends Controller
{
    public function __construct()
    {
        // Akses Role biasanya terbatas pada Super Admin
        $this->middleware('auth:sanctum');
        $this->middleware('role:SuperAdmin'); 
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

    public function destroy(Role $role)
    {   
        $role->delete();
        
        return response()->json(null, 204);
    }
}