<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Http\Resources\BannerResource;
use App\Http\Requests\StoreBannerRequest;
use App\Http\Requests\UpdateBannerRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Throwable;

class BannerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $data = Banner::orderByDesc('aktif_sampai')->paginate(12);
        return new JsonResponse(BannerResource::collection($data));
    }

    public function show(Banner $banner): JsonResponse
    {
        return new JsonResponse(new BannerResource($banner));
    }

    public function store(StoreBannerRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            if ($request->hasFile('foto')) {
                $validated['foto'] = $request->file('foto')
                    ->store('uploads/banner', 'public');
            }

            $banner = Banner::create($validated);

            return new JsonResponse(new BannerResource($banner), 201);
        } catch (Throwable $e) {
            if (!empty($validated['foto'] ?? null)) {
                Storage::disk('public')->delete($validated['foto']);
            }
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal menambahkan banner'
            ], 500);
        }
    }

    public function update(UpdateBannerRequest $request, Banner $banner): JsonResponse
    {
        $validated = $request->validated();

        try {
            if ($request->hasFile('foto')) {
                if ($banner->foto) {
                    Storage::disk('public')->delete($banner->foto);
                }
                $validated['foto'] = $request->file('foto')
                    ->store('uploads/banner', 'public');
            }

            $banner->update($validated);

            return new JsonResponse(new BannerResource($banner));
        } catch (Throwable $e) {
            if (!empty($validated['foto'] ?? null)) {
                Storage::disk('public')->delete($validated['foto']);
            }
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal memperbarui banner'
            ], 500);
        }
    }

    public function destroy(Banner $banner): JsonResponse
    {
        try {
            if ($banner->foto) {
                Storage::disk('public')->delete($banner->foto);
            }

            $banner->delete();

            return response()->json([
                'success'      => true,
                'message'      => 'Banner berhasil dihapus',
                'notification' => 'Berhasil dihapus',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success'      => false,
                'message'      => 'Gagal menghapus banner',
                'notification' => 'Gagal menghapus',
                'error'        => $e->getMessage(),
            ], 500);
        }
    }
}
