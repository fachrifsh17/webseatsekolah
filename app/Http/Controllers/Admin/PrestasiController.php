<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prestasi;
use App\Http\Resources\PrestasiResource;
use App\Http\Requests\StorePrestasiRequest;
use App\Http\Requests\UpdatePrestasiRequest;
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
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);
        
        $this->authorizeResource(Prestasi::class, 'prestasi');
    }

    public function index(): JsonResponse
    {
        try {
            $perPage = min((int) request()->get('per_page', 12), 100);
            $items   = Prestasi::orderBy('tahun', 'desc')->paginate($perPage);

            $paginationData = $items->toArray();

            return response()->json([
                'success' => true,
                'data'    => PrestasiResource::collection($items),
                'meta'    => [
                    'current_page'  => $paginationData['current_page'],
                    'last_page'     => $paginationData['last_page'],
                    'per_page'      => $paginationData['per_page'],
                    'total'         => $paginationData['total'],
                    'from'          => $paginationData['from'],
                    'to'            => $paginationData['to'],
                    'path'          => $paginationData['path'],
                    'next_page_url' => $paginationData['next_page_url'],
                    'prev_page_url' => $paginationData['prev_page_url'],
                    'links'         => array_map(function ($link) {
                        return [
                            'url'    => $link['url'],
                            'label'  => $link['label'],
                            'page'   => is_numeric($link['label']) ? (int) $link['label'] : null,
                            'active' => $link['active'],
                        ];
                    }, $paginationData['links']),
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
        $targetPath = public_path('uploads/prestasi');

        DB::beginTransaction();
        try {
            if ($request->hasFile('foto')) {
                $file = $request->file('foto');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $file->move($targetPath, $fileName);
                $validated['foto'] = $fileName;
            }

            $prestasi = Prestasi::create($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Prestasi berhasil ditambahkan.',
                'data'    => new PrestasiResource($prestasi),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            DB::rollBack();
            if (!empty($validated['foto'])) {
                $filePath = $targetPath . '/' . $validated['foto'];
                if (file_exists($filePath)) unlink($filePath);
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
        $targetPath = public_path('uploads/prestasi');
        $oldFoto = $prestasi->foto;

        DB::beginTransaction();
        try {
            if ($request->hasFile('foto')) {
                $file = $request->file('foto');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $file->move($targetPath, $fileName);
                $validated['foto'] = $fileName;
            }

            $prestasi->update($validated);
            DB::commit();

            if ($request->hasFile('foto') && $oldFoto) {
                $fullOldPath = $targetPath . '/' . str_replace('uploads/prestasi/', '', $oldFoto);
                if (file_exists($fullOldPath)) unlink($fullOldPath);
            }

            return response()->json([
                'success' => true,
                'message' => 'Prestasi berhasil diperbarui.',
                'data'    => new PrestasiResource($prestasi),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            if (isset($validated['foto']) && $validated['foto'] !== $oldFoto) {
                $tempPath = $targetPath . '/' . $validated['foto'];
                if (file_exists($tempPath)) unlink($tempPath);
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
        $oldFoto = $prestasi->foto;
        DB::beginTransaction();
        try {
            $prestasi->delete();
            DB::commit();

            if ($oldFoto) {
                $filePath = public_path('uploads/prestasi/') . str_replace('uploads/prestasi/', '', $oldFoto);
                if (file_exists($filePath)) unlink($filePath);
            }

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