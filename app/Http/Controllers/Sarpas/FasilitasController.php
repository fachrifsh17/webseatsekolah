<?php

namespace App\Http\Controllers\Sarpas;

use App\Http\Controllers\Controller;
use App\Models\Fasilitas;
use App\Http\Resources\FasilitasResource;
use App\Http\Requests\StoreFasilitasRequest;
use App\Http\Requests\UpdateFasilitasRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class FasilitasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
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
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'per_page'     => $data->perPage(),
                    'total'        => $data->total(),
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

        try {
            if ($request->hasFile('foto')) {
                $validated['foto'] = $request->file('foto')->store('uploads/fasilitas', 'public');
            }

            $fasilitas = Fasilitas::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Fasilitas berhasil ditambahkan.',
                'data'    => new FasilitasResource($fasilitas),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            if (!empty($validated['foto'] ?? null)) {
                Storage::disk('public')->delete($validated['foto']);
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

        try {
            if ($request->hasFile('foto')) {
                if ($fasilitas->foto) {
                    Storage::disk('public')->delete($fasilitas->foto);
                }
                $validated['foto'] = $request->file('foto')->store('uploads/fasilitas', 'public');
            }

            $fasilitas->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Fasilitas berhasil diperbarui.',
                'data'    => new FasilitasResource($fasilitas),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            if (!empty($validated['foto'] ?? null)) {
                Storage::disk('public')->delete($validated['foto']);
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
                Storage::disk('public')->delete($fasilitas->foto);
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