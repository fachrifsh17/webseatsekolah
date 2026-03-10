<?php

namespace App\Http\Controllers\Sarpas;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Http\Resources\AlbumResource;
use App\Http\Requests\StoreAlbumRequest;
use App\Http\Requests\UpdateAlbumRequest;
use Illuminate\Http\Request;
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
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);
        $this->authorizeResource(Album::class, 'album');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $search = $request->query('search');
            $perPage = min((int) $request->query('per_page', 12), 100);

            $query = Album::withCount('media');

            if (!empty(trim($search))) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama_album', 'LIKE', "%{$search}%")
                      ->orWhere('tanggal_kegiatan', 'LIKE', "%{$search}%");
                });
            }

            $data = $query->orderByDesc('tanggal_kegiatan')
                          ->orderByDesc('created_at')
                          ->paginate($perPage);

            $response = [
                'success' => true,
                'data'    => AlbumResource::collection($data),
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
            ];

            return response()->json($response, Response::HTTP_OK);
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
        $targetFolder = public_path('uploads/album');

        try {
            if ($request->hasFile('cover') && $request->file('cover')->isValid()) {
                $file = $request->file('cover');
                $fileName = time() . '_' . $file->getClientOriginalName();
                
                // Pindahkan langsung ke public/uploads/album
                $file->move($targetFolder, $fileName);
                
                // Simpan hanya nama filenya saja di DB (konsisten dengan diskusi sebelumnya)
                $validated['cover_path'] = $fileName;
            }

            $album = Album::create($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Album berhasil ditambahkan.',
                'data'    => new AlbumResource($album->fresh()->loadCount('media')),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            DB::rollBack();
            // Hapus file fisik jika transaksi gagal
            if (!empty($validated['cover_path']) && file_exists($targetFolder . '/' . $validated['cover_path'])) {
                unlink($targetFolder . '/' . $validated['cover_path']);
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
        $targetFolder = public_path('uploads/album');
        $newCoverFile = null;
        $originalCover = $album->getOriginal('cover_path');

        try {
            if ($request->hasFile('cover') && $request->file('cover')->isValid()) {
                $file = $request->file('cover');
                $newCoverFile = time() . '_' . $file->getClientOriginalName();
                
                $file->move($targetFolder, $newCoverFile);
                $validated['cover_path'] = $newCoverFile;
            }

            $album->fill($validated);
            $album->save();

            // Hapus cover lama jika ada cover baru dan file lama ada di public/uploads/album
            if ($newCoverFile && $originalCover && file_exists($targetFolder . '/' . $originalCover)) {
                unlink($targetFolder . '/' . $originalCover);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Album berhasil diperbarui.',
                'data'    => new AlbumResource($album->fresh()->loadCount('media')),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            if ($newCoverFile && file_exists($targetFolder . '/' . $newCoverFile)) {
                unlink($targetFolder . '/' . $newCoverFile);
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
        $targetFolder = public_path('uploads/album');

        try {
            // Hapus cover album
            if (!empty($album->cover_path) && file_exists($targetFolder . '/' . $album->cover_path)) {
                unlink($targetFolder . '/' . $album->cover_path);
            }

            // Hapus media terkait (asumsi folder media sama atau sesuaikan folder media jika berbeda)
            foreach ($album->media as $media) {
                $mediaTarget = public_path('uploads/media/' . $media->media_path);
                if (!empty($media->media_path) && file_exists($mediaTarget)) {
                    unlink($mediaTarget);
                }
                $media->delete();
            }

            $album->delete();
            DB::commit();

            return response()->json([
                'success'       => true,
                'message'       => 'Album berhasil dihapus',
                'notification'  => 'Berhasil dihapus'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Failed to delete album', [
                'album_id' => (string) $album->id,
                'error'    => $e->getMessage()
            ]);
            return response()->json([
                'success'       => false,
                'message'       => 'Gagal menghapus album',
                'notification'  => 'Gagal dihapus',
                'errors'        => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}