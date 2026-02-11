<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Http\Resources\AlbumResource;
use App\Http\Requests\StoreAlbumRequest;
use App\Http\Requests\UpdateAlbumRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class AlbumController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only(['store','update','destroy']);
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

            if (!empty($validated['cover_path']) && Storage::disk('public')->exists($validated['cover_path'])) {
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

        if (empty($validated)) {
            $validated = $request->only(['nama_album', 'tanggal_kegiatan']);
        }

        DB::beginTransaction();
        $newCoverPath   = null;
        $originalCover  = $album->getOriginal('cover_path');

        try {
            if ($request->hasFile('cover') && $request->file('cover')->isValid()) {
                $newCoverPath = $request->file('cover')->store('uploads/album', 'public');
                $validated['cover_path'] = $newCoverPath;
            }

            $album->fill($validated);
            $album->save();

            if ($newCoverPath && $originalCover && Storage::disk('public')->exists($originalCover)) {
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

            if ($newCoverPath && Storage::disk('public')->exists($newCoverPath)) {
                Storage::disk('public')->delete($newCoverPath);
            }

            Log::error('Failed to update album', [
                'album_id' => (string) $album->id,
                'payload'  => $validated,
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
            if (!empty($album->cover_path) && Storage::disk('public')->exists($album->cover_path)) {
                Storage::disk('public')->delete($album->cover_path);
            }

            foreach ($album->media as $media) {
                if (!empty($media->media_path) && Storage::disk('public')->exists($media->media_path)) {
                    Storage::disk('public')->delete($media->media_path);
                }
                $media->delete();
            }

            $album->delete();
            DB::commit();

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