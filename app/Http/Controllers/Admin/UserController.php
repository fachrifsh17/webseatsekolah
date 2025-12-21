<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;
use Throwable;

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
        return response()->json(UserResource::collection($users));
    }
    
    public function show(User $user): JsonResponse
    {
        return response()->json(new UserResource($user->load(['roles', 'guru'])));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            $user = User::create($data);

            if ($request->filled('role_id')) {
                $user->roles()->sync($request->role_id);
            }

            return response()->json(new UserResource($user->load(['roles', 'guru'])), 201);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menambahkan user'
            ], 500);
        }
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        try {
            $data = $request->validated();

            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            $user->update($data);

            if ($request->has('role_id')) {
                $user->roles()->sync($request->role_id);
            }

            return response()->json(new UserResource($user->load(['roles', 'guru'])));
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memperbarui user'
            ], 500);
        }
    }

    public function destroy(User $user): JsonResponse
    {
        try {
            if ($user->id === request()->user()->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak diizinkan menghapus akun yang sedang digunakan.'
                ], 403);
            }

            $user->delete();
            return response()->json(null, 204);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus user'
            ], 500);
        }
    }
}