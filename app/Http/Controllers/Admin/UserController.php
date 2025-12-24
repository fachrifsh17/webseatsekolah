<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;
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
        $users = User::with(['role', 'guru'])->paginate(15);
        return new JsonResponse(UserResource::collection($users));
    }
    
    public function show(User $user): JsonResponse
    {
        return new JsonResponse(new UserResource($user->load(['role', 'guru'])));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user = User::create($data);

        return new JsonResponse(new UserResource($user->load(['role', 'guru'])), 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return new JsonResponse(new UserResource($user->load(['role', 'guru'])));
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === request()->user()->id) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Anda tidak diizinkan menghapus akun yang sedang digunakan.'
            ], 403);
        }

        $user->delete();
        return new JsonResponse(null, 204);
    }
}