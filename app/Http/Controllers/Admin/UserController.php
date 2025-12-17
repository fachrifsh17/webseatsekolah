<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User; 
use App\Models\Role;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['roles', 'pegawai'])->paginate(15); 
        return UserResource::collection($users);
    }
    
    public function show(User $user)
    {
        $user->load(['roles', 'pegawai']);
        return new UserResource($user);
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        
        $data['password'] = Hash::make($request->password);
        
        $roleId = $request->input('role_id'); 
        
        $user = User::create($data);
        
        if ($roleId) {
            $user->roles()->attach($roleId);
        }
        
        return new UserResource($user->load(['roles', 'pegawai']));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        } else {
            unset($data['password']);
        }
        
        $roleId = $request->input('role_id'); 

        $user->update($data);
        
        if ($roleId) {
            $user->roles()->sync($roleId);
        }

        return new UserResource($user->load(['roles', 'pegawai']));
    }

    public function destroy(User $user)
    {
        $user->delete();
        
        return response()->json(null, 204);
    }
}