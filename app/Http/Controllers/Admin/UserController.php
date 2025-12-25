<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $users = User::with(['roles', 'guru'])->paginate(15);
        return UserResource::collection($users)->response();
    }
    
    public function show(User $user): JsonResponse
    {
        return new JsonResponse(new UserResource($user->load(['roles', 'guru'])));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return DB::transaction(function () use ($data) {
            $user = User::create($data);
            
            if (isset($data['role_ids'])) {
                $user->roles()->attach($data['role_ids']);
            }

            return new JsonResponse(new UserResource($user->load(['roles', 'guru'])), 201);
        });
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        return DB::transaction(function () use ($data, $user) {
            $user->update($data);

            if (isset($data['role_ids'])) {
                $user->roles()->sync($data['role_ids']);
            }

            return new JsonResponse(new UserResource($user->load(['roles', 'guru'])));
        });
    }

    public function destroy(User $user): JsonResponse
    {
        // Perbaikan utama: Menggunakan Auth::id() untuk menghilangkan error IDE
        if ($user->id === Auth::id()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Anda tidak diizinkan menghapus akun yang sedang digunakan.'
            ], 403);
        }

        $user->delete();
        return new JsonResponse(null, 204);
    }
}