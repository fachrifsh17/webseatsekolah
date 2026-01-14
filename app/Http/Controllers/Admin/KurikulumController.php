<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kurikulum;
use App\Http\Resources\KurikulumResource;
use App\Http\Requests\StoreKurikulumRequest;
use App\Http\Requests\UpdateKurikulumRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class KurikulumController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        try {
            $perPage = min((int) request()->get('per_page', 12), 100);
            $data    = Kurikulum::paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => KurikulumResource::collection($data),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'per_page'     => $data->perPage(),
                    'total'        => $data->total(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch kurikulum list', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar kurikulum',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    public function show(Kurikulum $kurikulum): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => new KurikulumResource($kurikulum),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch kurikulum detail', [
                'kurikulum_id' => (string) $kurikulum->id,
                'error'        => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail kurikulum',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreKurikulumRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('file_jadwal')) {
            $validated['file_jadwal_path'] = $request->file('file_jadwal')->store('uploads/kurikulum', 'public');
        }

        DB::beginTransaction();
        try {
            $kurikulum = Kurikulum::create($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Kurikulum berhasil ditambahkan.',
                'data'    => new KurikulumResource($kurikulum),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Failed to create kurikulum', ['payload' => $validated, 'error' => $e->getMessage()]);
            if (!empty($validated['file_jadwal_path'] ?? null)) {
                Storage::disk('public')->delete($validated['file_jadwal_path']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat kurikulum',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateKurikulumRequest $request, Kurikulum $kurikulum): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('file_jadwal')) {
            $newPath = $request->file('file_jadwal')->store('uploads/kurikulum', 'public');
            if ($newPath) {
                if ($kurikulum->file_jadwal_path) {
                    Storage::disk('public')->delete($kurikulum->file_jadwal_path);
                }
                $validated['file_jadwal_path'] = $newPath;
            }
        }

        DB::beginTransaction();
        try {
            $kurikulum->update($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Kurikulum berhasil diperbarui.',
                'data'    => new KurikulumResource($kurikulum),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Failed to update kurikulum', [
                'kurikulum_id' => (string) $kurikulum->id,
                'payload'      => $validated,
                'error'        => $e->getMessage()
            ]);
            if (!empty($validated['file_jadwal_path'] ?? null) && ($validated['file_jadwal_path'] !== $kurikulum->file_jadwal_path)) {
                Storage::disk('public')->delete($validated['file_jadwal_path']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui kurikulum',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Kurikulum $kurikulum): JsonResponse
    {
        DB::beginTransaction();
        try {
            if ($kurikulum->file_jadwal_path) {
                Storage::disk('public')->delete($kurikulum->file_jadwal_path);
            }

            $kurikulum->delete();
            DB::commit();

            return response()->json([
                'success'      => true,
                'message'      => 'Data kurikulum berhasil dihapus',
                'notification' => 'Berhasil dihapus'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Failed to delete kurikulum', [
                'kurikulum_id' => (string) $kurikulum->id,
                'error'        => $e->getMessage()
            ]);
            return response()->json([
                'success'      => false,
                'message'      => 'Gagal menghapus kurikulum',
                'notification' => 'Gagal dihapus',
                'errors'       => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
