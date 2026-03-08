<?php

namespace App\Http\Controllers\Humas;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Http\Resources\BannerResource;
use App\Http\Requests\StoreBannerRequest;
use App\Http\Requests\UpdateBannerRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class BannerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);
        $this->authorizeResource(Banner::class, 'banner');
    }

    public function index(): JsonResponse
    {
        try {
            $perPage = min((int) request()->get('per_page', 12), 100);
            $data = Banner::orderByDesc('aktif_sampai')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => BannerResource::collection($data),
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
            Log::error('Failed to fetch banners', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar banner',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Banner $banner): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => new BannerResource($banner),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch banner detail', [
                'banner_id' => (string) $banner->id,
                'error'     => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail banner',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreBannerRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $targetPath = public_path('uploads/banner');

        try {
            if ($request->hasFile('foto')) {
                $file = $request->file('foto');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $file->move($targetPath, $fileName);
                $validated['foto'] = $fileName;
            }

            $banner = Banner::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Banner berhasil ditambahkan.',
                'data'    => new BannerResource($banner),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            if (!empty($validated['foto'] ?? null)) {
                $filePath = $targetPath . '/' . $validated['foto'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            Log::error('Failed to create banner', ['payload' => $validated, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan banner',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateBannerRequest $request, Banner $banner): JsonResponse
    {
        $validated = $request->validated();
        $targetPath = public_path('uploads/banner');

        try {
            if ($request->hasFile('foto')) {
                $oldFoto = $banner->foto;
                if ($oldFoto) {
                    $oldFilePath = $targetPath . '/' . str_replace('uploads/banner/', '', $oldFoto);
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }

                $file = $request->file('foto');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $file->move($targetPath, $fileName);
                $validated['foto'] = $fileName;
            }

            $banner->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Banner berhasil diperbarui.',
                'data'    => new BannerResource($banner),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            if (!empty($validated['foto'] ?? null)) {
                $tempPath = $targetPath . '/' . $validated['foto'];
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
            }
            Log::error('Failed to update banner', [
                'banner_id' => (string) $banner->id,
                'payload'   => $validated,
                'error'     => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui banner',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Banner $banner): JsonResponse
    {
        try {
            if ($banner->foto) {
                $filePath = public_path('uploads/banner/') . str_replace('uploads/banner/', '', $banner->foto);
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            $banner->delete();

            return response()->json([
                'success'      => true,
                'message'      => 'Banner berhasil dihapus',
                'notification' => 'Berhasil dihapus',
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to delete banner', [
                'banner_id' => (string) $banner->id,
                'error'     => $e->getMessage()
            ]);
            return response()->json([
                'success'      => false,
                'message'      => 'Gagal menghapus banner',
                'notification' => 'Gagal dihapus',
                'errors'       => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}