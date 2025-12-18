<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User; 
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Http\JsonResponse;

class UserController extends Controller implements HasMiddleware
{
       public static function middleware(): array
    {
        return [
            new Middleware('auth.token'),
            new Middleware('role:SuperAdmin'), 
            new Middleware('log.admin', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index()
    {
        $users = User::with(['roles', 'guru'])->paginate(15); 
        return UserResource::collection($users);
    }
    
    public function show(User $user)
    {
        return new UserResource($user->load(['roles', 'guru']));
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($request->password);
        
        $user = User::create($data);
        
        if ($request->filled('role_id')) {
            $user->roles()->sync($request->role_id);
        }
        
        return new UserResource($user->load(['roles', 'guru']));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        } else {
            unset($data['password']);
        }
        
        $user->update($data);
        
        if ($request->has('role_id')) {
            $user->roles()->sync($request->role_id);
        }

        return new UserResource($user->load(['roles', 'guru']));
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === request()->user()->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak diizinkan menghapus akun yang sedang digunakan.'
            ], 403);
        }

        $user->delete();
        return response()->json(null, 204);
    }
}