<?php

namespace App\Http\Controllers\Humas;

use App\Http\Controllers\Controller;
use App\Models\Prestasi;
use App\Http\Resources\PrestasiResource;
use App\Http\Requests\StorePrestasiRequest;
use App\Http\Requests\UpdatePrestasiRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class PrestasiController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);

        // Mengotomatisasi pengecekan Policy (index, show, store, update, destroy)
        $this->authorizeResource(Prestasi::class, 'prestasi');
    }

    public function index(): JsonResponse
    {
        try {
            $perPage = min((int) request()->get('per_page', 12), 100);
            $items   = Prestasi::orderBy('tahun', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => PrestasiResource::collection($items),
                'meta'    => [
                    'current_page' => $items->currentPage(),
                    'last_page'    => $items->lastPage(),
                    'per_page'     => $items->perPage(),
                    'total'        => $items->total(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch prestasi list', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar prestasi',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    public function show(Prestasi $prestasi): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => new PrestasiResource($prestasi),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch prestasi detail', [
                'prestasi_id' => (string) $prestasi->id,
                'error'       => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail prestasi',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StorePrestasiRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/prestasi', 'public');
        }

        DB::beginTransaction();
        try {
            $prestasi = Prestasi::create($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Prestasi berhasil ditambahkan.',
                'data'    => new PrestasiResource($prestasi),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            DB::rollBack();
            if (!empty($validated['foto'] ?? null)) {
                Storage::disk('public')->delete($validated['foto']);
            }
            Log::error('Failed to create prestasi', ['payload' => $validated, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat prestasi',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdatePrestasiRequest $request, Prestasi $prestasi): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('foto')) {
            $newPath = $request->file('foto')->store('uploads/prestasi', 'public');
            if ($newPath) {
                if ($prestasi->foto) {
                    Storage::disk('public')->delete($prestasi->foto);
                }
                $validated['foto'] = $newPath;
            }
        }

        DB::beginTransaction();
        try {
            $prestasi->update($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Prestasi berhasil diperbarui.',
                'data'    => new PrestasiResource($prestasi),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            // Hapus file baru jika proses DB gagal
            if (!empty($validated['foto'] ?? null) && ($validated['foto'] !== $prestasi->foto)) {
                Storage::disk('public')->delete($validated['foto']);
            }
            Log::error('Failed to update prestasi', [
                'prestasi_id' => (string) $prestasi->id,
                'payload'     => $validated,
                'error'       => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui prestasi',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Prestasi $prestasi): JsonResponse
    {
        DB::beginTransaction();
        try {
            if ($prestasi->foto) {
                Storage::disk('public')->delete($prestasi->foto);
            }

            $prestasi->delete();
            DB::commit();

            return response()->json([
                'success'      => true,
                'message'      => 'Prestasi berhasil dihapus',
                'notification' => 'Berhasil dihapus'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Failed to delete prestasi', [
                'prestasi_id' => (string) $prestasi->id,
                'error'       => $e->getMessage()
            ]);
            return response()->json([
                'success'      => false,
                'message'      => 'Gagal menghapus prestasi',
                'notification' => 'Gagal dihapus',
                'errors'       => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}