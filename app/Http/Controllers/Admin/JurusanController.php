<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Jurusan;
use App\Http\Resources\JurusanResource;
use App\Http\Requests\StoreJurusanRequest;
use App\Http\Requests\UpdateJurusanRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            $data = Jurusan::orderBy('created_at', 'desc')->paginate(12);
            $paginationData = $data->toArray();

            return response()->json([
                'success' => true,
                'data'    => JurusanResource::collection($data),
                'meta'    => [
                    'current_page'  => $data->currentPage(),
                    'last_page'     => $data->lastPage(),
                    'per_page'      => $data->perPage(),
                    'total'         => $data->total(),
                    'from'          => $data->firstItem(),
                    'to'            => $data->lastItem(),
                    'next_page_url' => $data->nextPageUrl(),
                    'prev_page_url' => $data->previousPageUrl(),
                    'path'          => $paginationData['path'],
                    'links'         => $paginationData['links'],
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
        $targetPath = public_path('uploads/jurusan');

        if (Jurusan::where('nama_jurusan', $validated['nama_jurusan'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Nama jurusan sudah terdaftar.',
            ], Response::HTTP_CONFLICT);
        }

        try {
            if ($request->hasFile('foto')) {
                $file = $request->file('foto');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $file->move($targetPath, $fileName);
                $validated['foto'] = $fileName;
            }

            $jurusan = DB::transaction(fn() => Jurusan::create($validated));
            $jurusan->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Jurusan berhasil ditambahkan',
                'data'    => new JurusanResource($jurusan),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Failed to create jurusan', ['payload' => $validated, 'error' => $e->getMessage()]);
            if (!empty($validated['foto'])) {
                $filePath = $targetPath . '/' . $validated['foto'];
                if (file_exists($filePath)) unlink($filePath);
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
        $targetPath = public_path('uploads/jurusan');
        $oldFoto = $jurusan->foto;

        if (!empty($validated['nama_jurusan'])) {
            $exists = Jurusan::where('nama_jurusan', $validated['nama_jurusan'])
                ->where('id', '!=', $jurusan->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nama jurusan sudah digunakan oleh data lain.',
                ], Response::HTTP_CONFLICT);
            }
        }

        try {
            if ($request->hasFile('foto')) {
                if ($oldFoto) {
                    $oldPath = $targetPath . '/' . str_replace('uploads/jurusan/', '', $oldFoto);
                    if (file_exists($oldPath)) unlink($oldPath);
                }

                $file = $request->file('foto');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $file->move($targetPath, $fileName);
                $validated['foto'] = $fileName;
            }

            DB::transaction(fn() => $jurusan->update($validated));
            $jurusan->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Jurusan berhasil diperbarui',
                'data'    => new JurusanResource($jurusan),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to update jurusan', ['jurusan_id' => (string) $jurusan->id, 'payload' => $validated, 'error' => $e->getMessage()]);
            if (!empty($validated['foto']) && ($validated['foto'] !== $oldFoto)) {
                $tempPath = $targetPath . '/' . $validated['foto'];
                if (file_exists($tempPath)) unlink($tempPath);
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
                    $filePath = public_path('uploads/jurusan/') . str_replace('uploads/jurusan/', '', $jurusan->foto);
                    if (file_exists($filePath)) unlink($filePath);
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