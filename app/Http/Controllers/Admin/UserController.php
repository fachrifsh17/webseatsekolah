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
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Throwable;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy', 'bulkDestroy']);

        $this->authorizeResource(User::class, 'user');
    }

    public function index(Request $request): JsonResponse
    {
        $query = User::with(['roles', 'guruStaf', 'siswa']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('username', 'like', "%{$search}%");
        }

        if ($request->filled('role')) {
            $role = $request->input('role');
            $query->whereHas('roles', function($q) use ($role) {
                $q->where('role_name', $role)
                  ->orWhere('nama', $role)
                  ->orWhere('slug', $role);
            });
        }

        $users = $query->orderBy('username', 'asc')->paginate(15);
        
        return response()->json([
            'success' => true,
            'data'    => UserResource::collection($users),
            'meta'    => $this->formatPagination($users),
        ], Response::HTTP_OK);
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['roles', 'guruStaf', 'siswa']);

        return response()->json([
            'success' => true,
            'data'    => new UserResource($user),
        ], Response::HTTP_OK);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password'] ?? $data['username']);

        try {
            $user = DB::transaction(function () use ($data) {
                $user = User::create($data);

                if (!empty($data['role_ids']) && is_array($data['role_ids'])) {
                    $roleIds = array_map('strval', $data['role_ids']);
                    $user->roles()->attach($roleIds);

                    $firstRole = $user->roles()->first();
                    if ($firstRole) {
                        $roleValue = $firstRole->role_name ?? $firstRole->nama ?? $firstRole->slug;
                        $user->update(['current_role' => $roleValue]);
                    }
                }

                return $user;
            });

            $user->load(['roles', 'guruStaf', 'siswa']);

            return response()->json([
                'success' => true,
                'message' => 'User berhasil ditambahkan.',
                'data'    => new UserResource($user),
            ], Response::HTTP_CREATED);
        } catch (QueryException $e) {
            return $this->handleQueryException($e);
        } catch (Throwable $e) {
            Log::error('Failed to create user', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal membuat user.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        try {
            DB::transaction(function () use ($data, $user) {
                $user->update($data);

                if (array_key_exists('role_ids', $data)) {
                    $roleIds = is_array($data['role_ids']) ? array_map('strval', $data['role_ids']) : [];
                    $user->roles()->sync($roleIds);

                    $availableRoles = $user->roles()->get()->map(function($role) {
                        return $role->role_name ?? $role->nama ?? $role->slug;
                    })->filter()->toArray();

                    if (!empty($availableRoles) && !in_array($user->current_role, $availableRoles)) {
                        $user->update(['current_role' => $availableRoles[0]]);
                    } elseif (empty($availableRoles)) {
                        $user->update(['current_role' => null]);
                    }
                }
            });

            $user->load(['roles', 'guruStaf', 'siswa']);

            return response()->json([
                'success' => true,
                'message' => 'User berhasil diperbarui.',
                'data'    => new UserResource($user),
            ], Response::HTTP_OK);
        } catch (QueryException $e) {
            return $this->handleQueryException($e, $user->id);
        } catch (Throwable $e) {
            Log::error('Failed to update user', ['user_id' => (string)$user->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui user.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(User $user): JsonResponse
    {
        if ((string)$user->id === (string)Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak diizinkan menghapus akun yang sedang digunakan.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            DB::transaction(function () use ($user) {
                if ($user->guruStaf) $user->guruStaf->delete();
                if ($user->siswa) $user->siswa->delete();
                if ($user->orangtua) $user->orangtua->delete();
                
                $user->delete();
            });

            return response()->json(['success' => true, 'message' => 'User berhasil dihapus.'], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to delete user', ['user_id' => (string)$user->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal menghapus user.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'string|exists:users,id'
        ]);

        $ids = $request->input('ids');
        $currentUserId = (string)Auth::id();

        if (in_array($currentUserId, $ids)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak bisa menghapus akun sendiri dalam operasi massal.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            DB::transaction(function () use ($ids) {
                $users = User::with(['guruStaf', 'siswa', 'orangtua'])->whereIn('id', $ids)->get();
                /** @var \App\Models\User $user */
                foreach ($users as $user) {
                    if ($user->guruStaf) $user->guruStaf->delete();
                    if ($user->siswa) $user->siswa->delete();
                    if ($user->orangtua) $user->orangtua->delete();
                    $user->delete();
                }
            });

            return response()->json(['success' => true, 'message' => count($ids) . ' user berhasil dihapus.'], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Bulk delete failed', ['ids' => $ids, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal menghapus beberapa user.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function handleQueryException(QueryException $e, $userId = null)
    {
        Log::error('Database Error', ['user_id' => (string)$userId, 'error' => $e->getMessage()]);
        if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
            return response()->json([
                'success' => false,
                'message' => 'Username sudah terdaftar.',
                'errors'  => ['username' => ['Username sudah digunakan.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        return response()->json(['success' => false, 'message' => 'Terjadi kesalahan database.'], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    private function formatPagination($paginator)
    {
        $data = $paginator->toArray();
        return [
            'current_page' => $data['current_page'],
            'last_page'    => $data['last_page'],
            'per_page'     => $data['per_page'],
            'total'        => $data['total'],
            'links'        => array_map(fn($link) => [
                'url'    => $link['url'],
                'label'  => $link['label'],
                'active' => $link['active'],
                'page'   => is_numeric($link['label']) ? (int) $link['label'] : null,
            ], $data['links']),
        ];
    }
}