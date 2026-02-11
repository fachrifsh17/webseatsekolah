<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Jurusan;
use App\Http\Resources\JurusanResource;
use App\Http\Requests\StoreJurusanRequest;
use App\Http\Requests\UpdateJurusanRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class JurusanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);
        $this->authorizeResource(Jurusan::class, 'jurusan');
    }

    public function index(): JsonResponse
    {
        try {
            $data = Jurusan::paginate(12);

            return response()->json([
                'success' => true,
                'data'    => JurusanResource::collection($data),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'per_page'     => $data->perPage(),
                    'total'        => $data->total(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch jurusan', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar jurusan',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Jurusan $jurusan): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => new JurusanResource($jurusan),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch jurusan detail', ['jurusan_id' => (string) $jurusan->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail jurusan',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreJurusanRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/jurusan', 'public');
        }

        try {
            $jurusan = DB::transaction(fn() => Jurusan::create($validated));
            $jurusan->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Jurusan berhasil ditambahkan',
                'data'    => new JurusanResource($jurusan),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Failed to create jurusan', ['payload' => $validated, 'error' => $e->getMessage()]);
            if (!empty($validated['foto'] ?? null)) {
                Storage::disk('public')->delete($validated['foto']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat jurusan',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateJurusanRequest $request, Jurusan $jurusan): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('foto')) {
            $newPath = $request->file('foto')->store('uploads/jurusan', 'public');
            if ($newPath) {
                if ($jurusan->foto) {
                    Storage::disk('public')->delete($jurusan->foto);
                }
                $validated['foto'] = $newPath;
            }
        }

        try {
            DB::transaction(fn() => $jurusan->update($validated));
            $jurusan->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Jurusan berhasil diperbarui',
                'data'    => new JurusanResource($jurusan),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to update jurusan', ['jurusan_id' => (string) $jurusan->id, 'payload' => $validated, 'error' => $e->getMessage()]);
            if (!empty($validated['foto'] ?? null) && ($validated['foto'] !== $jurusan->foto)) {
                Storage::disk('public')->delete($validated['foto']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui jurusan',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Jurusan $jurusan): JsonResponse
    {
        try {
            DB::transaction(function () use ($jurusan) {
                if ($jurusan->foto) {
                    Storage::disk('public')->delete($jurusan->foto);
                }
                $jurusan->delete();
            });

            return response()->json([
                'success'      => true,
                'message'      => 'Jurusan berhasil dihapus',
                'notification' => 'Berhasil dihapus'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to delete jurusan', ['jurusan_id' => (string) $jurusan->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus jurusan',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}