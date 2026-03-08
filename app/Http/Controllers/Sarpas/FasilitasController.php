<?php

namespace App\Http\Controllers\Sarpas;

use App\Http\Controllers\Controller;
use App\Models\Fasilitas;
use App\Http\Resources\FasilitasResource;
use App\Http\Requests\StoreFasilitasRequest;
use App\Http\Requests\UpdateFasilitasRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class FasilitasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);
        $this->authorizeResource(Fasilitas::class, 'fasilitas');
    }

    public function index(): JsonResponse
    {
        try {
            $perPage = min((int) request()->get('per_page', 12), 100);
            $data    = Fasilitas::paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => FasilitasResource::collection($data),
                'meta'    => [
                    'current_page'  => $data->currentPage(),
                    'last_page'     => $data->lastPage(),
                    'per_page'      => $data->perPage(),
                    'total'         => $data->total(),
                    'from'          => $data->firstItem(),
                    'to'            => $data->lastItem(),
                    'next_page_url' => $data->nextPageUrl(),
                    'prev_page_url' => $data->previousPageUrl(),
                    'path'          => $data->path(),
                    'links'         => $data->linkCollection()->toArray(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch fasilitas list', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar fasilitas',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    public function show(Fasilitas $fasilitas): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => new FasilitasResource($fasilitas),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch fasilitas detail', [
                'fasilitas_id' => (string) $fasilitas->id,
                'error'        => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail fasilitas',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreFasilitasRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $targetPath = public_path('uploads/fasilitas');

        try {
            if ($request->hasFile('foto')) {
                $file = $request->file('foto');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $file->move($targetPath, $fileName);
                $validated['foto'] = $fileName;
            }

            $fasilitas = Fasilitas::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Fasilitas berhasil ditambahkan.',
                'data'    => new FasilitasResource($fasilitas),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            if (!empty($validated['foto'])) {
                $filePath = $targetPath . '/' . $validated['foto'];
                if (file_exists($filePath)) unlink($filePath);
            }
            Log::error('Failed to create fasilitas', ['payload' => $validated, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan fasilitas',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateFasilitasRequest $request, Fasilitas $fasilitas): JsonResponse
    {
        $validated = $request->validated();
        $targetPath = public_path('uploads/fasilitas');
        $oldFoto = $fasilitas->foto;

        try {
            if ($request->hasFile('foto')) {
                if ($oldFoto) {
                    $oldPath = $targetPath . '/' . str_replace('uploads/fasilitas/', '', $oldFoto);
                    if (file_exists($oldPath)) unlink($oldPath);
                }

                $file = $request->file('foto');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $file->move($targetPath, $fileName);
                $validated['foto'] = $fileName;
            }

            $fasilitas->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Fasilitas berhasil diperbarui.',
                'data'    => new FasilitasResource($fasilitas),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            if (!empty($validated['foto']) && $validated['foto'] !== $oldFoto) {
                $tempPath = $targetPath . '/' . $validated['foto'];
                if (file_exists($tempPath)) unlink($tempPath);
            }
            Log::error('Failed to update fasilitas', [
                'fasilitas_id' => (string) $fasilitas->id,
                'payload'      => $validated,
                'error'        => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui fasilitas',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Fasilitas $fasilitas): JsonResponse
    {
        try {
            if ($fasilitas->foto) {
                $filePath = public_path('uploads/fasilitas/') . str_replace('uploads/fasilitas/', '', $fasilitas->foto);
                if (file_exists($filePath)) unlink($filePath);
            }

            $fasilitas->delete();

            return response()->json([
                'success'      => true,
                'message'      => 'Fasilitas berhasil dihapus',
                'notification' => 'Berhasil dihapus'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to delete fasilitas', [
                'fasilitas_id' => (string) $fasilitas->id,
                'error'        => $e->getMessage()
            ]);
            return response()->json([
                'success'      => false,
                'message'      => 'Gagal menghapus fasilitas',
                'notification' => 'Gagal dihapus',
                'errors'       => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}