<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User; // Asumsi Model bernama User
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UserApiController extends Controller
{
    public function index()
    {
        $users = User::with('role')->latest()->paginate(10);
        return UserResource::collection($users);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:50|unique:users,username',
            'password' => 'required|string|min:6|confirmed',
            'nama_lengkap' => 'required|string|max:150',
            'role_id' => 'required|exists:roles,id',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();
        $data['password'] = Hash::make($request->password); // WAJIB: Hash Password

        $user = User::create($data);
        
        return response()->json([
            'message' => 'User berhasil ditambahkan.',
            'data' => new UserResource($user->load('role'))
        ], 201);
    }
    public function show(User $user)
    {
        return new UserResource($user->load('role'));
    }
    public function update(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:50|unique:users,username,' . $user->id,
            'password' => 'nullable|string|min:6|confirmed', // Password opsional saat update
            'nama_lengkap' => 'required|string|max:150',
            'role_id' => 'required|exists:roles,id',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();
        
        // Hanya hash jika password disediakan
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        } else {
            unset($data['password']); 
        }

        $user->update($data);

        return response()->json([
            'message' => 'User berhasil diperbarui.',
            'data' => new UserResource($user->load('role'))
        ]);
    }
    public function destroy(User $user)
    {
        $user->delete();
        return response()->json(null, 204);
    }
}