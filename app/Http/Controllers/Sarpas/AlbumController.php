<?php

namespace App\Http\Controllers\Sarpas;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Http\Resources\AlbumResource;
use App\Http\Requests\{StoreAlbumRequest, UpdateAlbumRequest};
use Illuminate\Support\Facades\{Storage, DB, Log};
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class AlbumController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);

        $this->authorizeResource(Album::class, 'album');
    }

    public function index(): JsonResponse
    {
        try {
            $perPage = min((int) request()->get('per_page', 12), 100);
            $data = Album::with('media')->orderByDesc('tanggal_kegiatan')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => AlbumResource::collection($data),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'per_page'     => $data->perPage(),
                    'total'        => $data->total(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch album list', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar album',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Album $album): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => new AlbumResource($album->load('media')),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch album detail', [
                'album_id' => (string) $album->id,
                'error'    => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail album',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreAlbumRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['tanggal_kegiatan'] = $validated['tanggal_kegiatan'] ?? null;

        DB::beginTransaction();
        try {
            if ($request->hasFile('cover') && $request->file('cover')->isValid()) {
                $validated['cover_path'] = $request->file('cover')->store('uploads/album', 'public');
            }

            $album = Album::create($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Album berhasil ditambahkan.',
                'data'    => new AlbumResource($album->fresh()->load('media')),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            DB::rollBack();

            if (!empty($validated['cover_path'])) {
                Storage::disk('public')->delete($validated['cover_path']);
            }

            Log::error('Failed to create album', ['payload' => $validated, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan album',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateAlbumRequest $request, Album $album): JsonResponse
    {
        $validated = $request->validated();

        if ($request->has('tanggal_kegiatan')) {
            $validated['tanggal_kegiatan'] = $request->input('tanggal_kegiatan') === '' ? null : $request->input('tanggal_kegiatan');
        }

        DB::beginTransaction();
        $newCoverPath   = null;
        $originalCover  = $album->getOriginal('cover_path');

        try {
            if ($request->hasFile('cover') && $request->file('cover')->isValid()) {
                $newCoverPath = $request->file('cover')->store('uploads/album', 'public');
                $validated['cover_path'] = $newCoverPath;
            }

            $album->update($validated);

            if ($newCoverPath && $originalCover) {
                Storage::disk('public')->delete($originalCover);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Album berhasil diperbarui.',
                'data'    => new AlbumResource($album->fresh()->load('media')),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();

            if ($newCoverPath) {
                Storage::disk('public')->delete($newCoverPath);
            }

            Log::error('Failed to update album', [
                'album_id' => (string) $album->id,
                'error'    => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui album',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Album $album): JsonResponse
    {
        DB::beginTransaction();
        try {
            $coverPath = $album->cover_path;
            $mediaFiles = $album->media->pluck('media_path')->toArray();

            foreach ($album->media as $media) {
                $media->delete();
            }

            $album->delete();
            DB::commit();

            if ($coverPath) {
                Storage::disk('public')->delete($coverPath);
            }

            foreach ($mediaFiles as $path) {
                if ($path) Storage::disk('public')->delete($path);
            }

            return response()->json([
                'success'      => true,
                'message'      => 'Album berhasil dihapus',
                'notification' => 'Berhasil dihapus'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Failed to delete album', [
                'album_id' => (string) $album->id,
                'error'    => $e->getMessage()
            ]);

            return response()->json([
                'success'      => false,
                'message'      => 'Gagal menghapus album',
                'notification' => 'Gagal dihapus',
                'errors'       => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}