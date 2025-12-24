<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Http\Resources\AlbumResource;
use App\Http\Requests\StoreAlbumRequest;
use App\Http\Requests\UpdateAlbumRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Throwable;

class AlbumController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin,Guru');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $data = Album::orderByDesc('tanggal_kegiatan')->paginate(12);
        return new JsonResponse(AlbumResource::collection($data));
    }

    public function show(Album $album): JsonResponse
    {
        return new JsonResponse(new AlbumResource($album));
    }

    public function store(StoreAlbumRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            if ($request->hasFile('cover')) {
                $validated['cover_path'] = $request->file('cover')
                    ->store('uploads/album', 'public');
            }

            $album = Album::create($validated);

            return new JsonResponse(new AlbumResource($album), 201);
        } catch (Throwable $e) {
            if (!empty($validated['cover_path'] ?? null)) {
                Storage::disk('public')->delete($validated['cover_path']);
            }
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal menambahkan album'
            ], 500);
        }
    }

    public function update(UpdateAlbumRequest $request, Album $album): JsonResponse
    {
        $validated = $request->validated();

        try {
            if ($request->hasFile('cover')) {
                if ($album->cover_path) {
                    Storage::disk('public')->delete($album->cover_path);
                }
                $validated['cover_path'] = $request->file('cover')
                    ->store('uploads/album', 'public');
            }

            $album->update($validated);

            return new JsonResponse(new AlbumResource($album));
        } catch (Throwable $e) {
            if (!empty($validated['cover_path'] ?? null)) {
                Storage::disk('public')->delete($validated['cover_path']);
            }
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal memperbarui album'
            ], 500);
        }
    }

    public function destroy(Album $album): JsonResponse
    {
        try {
            if ($album->cover_path) {
                Storage::disk('public')->delete($album->cover_path);
            }

            $album->delete();

            return new JsonResponse(null, 204);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal menghapus album'
            ], 500);
        }
    }
}