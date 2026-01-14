<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuruStaf;
use App\Http\Resources\GuruResource;
use App\Http\Requests\StoreGuruRequest;
use App\Http\Requests\UpdateGuruRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class GuruController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $data = GuruStaf::with(['jurusan', 'user'])->paginate(12);

        return response()->json([
            'success' => true,
            'data'    => GuruResource::collection($data),
            'meta'    => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'per_page'     => $data->perPage(),
                'total'        => $data->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function search(Request $request): JsonResponse
    {
        $search = $request->get('q');

        $gurus = GuruStaf::query()
            ->when($search, function ($query, $search) {
                $query->where('nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('nip', 'like', "%{$search}%");
            })
            ->select('id', 'nama_lengkap', 'nip')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $gurus,
        ], Response::HTTP_OK);
    }

    public function show(?GuruStaf $guru): JsonResponse
    {
        if (!$guru) {
            return response()->json([
                'success' => false,
                'message' => 'Data guru tidak ditemukan',
                'errors'  => ['id' => ['Guru dengan ID tersebut tidak ada']],
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data'    => new GuruResource($guru->load(['jurusan', 'user'])),
        ], Response::HTTP_OK);
    }

    public function store(StoreGuruRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/guru', 'public');
        }

        if (empty($validated['user_id'])) {
            $validated['user_id'] = (string) Auth::id();
        }

        try {
            $guru = DB::transaction(function () use ($validated) {
                return GuruStaf::create($validated);
            });

            return response()->json([
                'success' => true,
                'message' => 'Data guru berhasil ditambahkan.',
                'data'    => new GuruResource($guru->load(['jurusan', 'user'])),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Failed to create guru', ['error' => $e->getMessage()]);

            if (!empty($validated['foto'])) {
                Storage::disk('public')->delete($validated['foto']);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat data guru.',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateGuruRequest $request, ?GuruStaf $guru): JsonResponse
    {
        if (!$guru) {
            return response()->json([
                'success' => false,
                'message' => 'Data guru tidak ditemukan',
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();
        $oldFoto = $guru->foto;

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/guru', 'public');
        }

        if ($request->filled('foto') && $request->foto === 'null') {
            if ($oldFoto) {
                Storage::disk('public')->delete($oldFoto);
            }
            $validated['foto'] = null;
        }

        try {
            DB::transaction(function () use ($guru, $validated) {
                $guru->update($validated);
            });

            if ($request->hasFile('foto') && $oldFoto && $oldFoto !== $validated['foto']) {
                Storage::disk('public')->delete($oldFoto);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data guru berhasil diperbarui.',
                'data'    => new GuruResource($guru->refresh()->load(['jurusan', 'user'])),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to update guru', ['error' => $e->getMessage()]);

            if ($request->hasFile('foto') && isset($validated['foto'])) {
                Storage::disk('public')->delete($validated['foto']);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data guru.',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(?GuruStaf $guru): JsonResponse
    {
        if (!$guru) {
            return response()->json([
                'success' => false,
                'message' => 'Data guru tidak ditemukan',
            ], Response::HTTP_NOT_FOUND);
        }

        $oldFoto = $guru->foto;

        try {
            $blockers = [];

            $relations = [
                'user' => 'Terdapat akun user yang terhubung',
                'guruMapel' => 'Terhubung dengan data guru_mapel',
                'presensiGuruMapel' => 'Memiliki data presensi per mapel',
                'presensi' => 'Memiliki data presensi',
                'jadwalMengajar' => 'Memiliki jadwal mengajar',
                'strukturJabatan' => 'Memiliki struktur jabatan',
                'jadwalProduktif' => 'Memiliki jadwal produktif',
                'poinGuru' => 'Memiliki data poin'
            ];

            foreach ($relations as $method => $message) {
                if (method_exists($guru, $method) && $guru->$method()->exists()) {
                    $blockers[] = $message;
                }
            }

            if (!empty($blockers)) {
                return response()->json([
                    'success'    => false,
                    'error_code' => 'conflict_relations',
                    'message'    => 'Gagal menghapus: data masih terhubung dengan resource lain.',
                    'details'    => array_values(array_unique($blockers)),
                ], Response::HTTP_CONFLICT);
            }

            DB::transaction(function () use ($guru) {
                if (method_exists($guru, 'mapel')) {
                    $guru->mapel()->detach();
                }
                $guru->delete();
            });

            if ($oldFoto) {
                Storage::disk('public')->delete($oldFoto);
            }

            return response()->json([
                'success'      => true,
                'message'      => 'Data guru berhasil dihapus',
                'notification' => 'Berhasil dihapus',
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to delete guru', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data guru',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}