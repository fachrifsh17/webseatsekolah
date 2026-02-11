<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Http\Resources\MediaResource;
use App\Http\Requests\StoreMediaRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class MediaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);

        // Proteksi Policy tetap aktif
        $this->authorizeResource(Media::class, 'media');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $albumId = $request->query('album_id');
            $perPage = min((int) $request->get('per_page', 20), 100);

            $query = Media::with('album');
            if ($albumId) {
                $query->where('album_id', $albumId);
            }

            $data = $query->latest()->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => MediaResource::collection($data),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'per_page'     => $data->perPage(),
                    'total'        => $data->total(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch media list', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar media',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Media $media): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new MediaResource($media->load('album'))
        ], Response::HTTP_OK);
    }

    public function store(StoreMediaRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $jenis = strtolower($validated['jenis_media']);

        DB::beginTransaction();
        try {
            $mediaCollection = [];

            if ($jenis === 'foto' && $request->hasFile('media')) {
                $files = is_array($request->file('media')) ? $request->file('media') : [$request->file('media')];
                foreach ($files as $file) {
                    if ($file->isValid()) {
                        $path = $file->store('uploads/media', 'public');
                        $media = Media::create([
                            'album_id'    => $validated['album_id'],
                            'media_path'  => $path,
                            'jenis_media' => 'Foto',
                            'keterangan'  => $validated['keterangan'] ?? null,
                        ]);
                        $mediaCollection[] = new MediaResource($media->load('album'));
                    }
                }
            } else {
                $path = $validated['media_path'] ?? null;
                if ($request->hasFile('media') && $request->file('media')->isValid()) {
                    $path = $request->file('media')->store('uploads/media', 'public');
                }

                $media = Media::create([
                    'album_id'    => $validated['album_id'],
                    'media_path'  => $path,
                    'jenis_media' => ucfirst($jenis),
                    'keterangan'  => $validated['keterangan'] ?? null,
                ]);
                $mediaCollection[] = new MediaResource($media->load('album'));
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Media berhasil ditambahkan.',
                'data'    => $mediaCollection,
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Media Store Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal menyimpan media'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request, Media $media): JsonResponse
    {
        $validated = $request->validate([
            'album_id'    => ['sometimes', 'string', 'exists:album,id'],
            'jenis_media' => ['sometimes', 'string', 'in:foto,video,Foto,Video'],
            'keterangan'  => ['sometimes', 'nullable', 'string'],
            'media'       => ['sometimes', 'file', 'max:10240'],
            'media_path'  => ['sometimes', 'nullable', 'string'],
        ]);

        DB::beginTransaction();
        try {
            if ($request->hasFile('media') && $request->file('media')->isValid()) {
                if ($media->media_path && !str_starts_with($media->media_path, 'http')) {
                    Storage::disk('public')->delete($media->media_path);
                }
                $validated['media_path'] = $request->file('media')->store('uploads/media', 'public');
            }

            $media->update([
                'album_id'    => $validated['album_id'] ?? $media->album_id,
                'jenis_media' => isset($validated['jenis_media']) ? ucfirst(strtolower($validated['jenis_media'])) : $media->jenis_media,
                'keterangan'  => $validated['keterangan'] ?? $media->keterangan,
                'media_path'  => $validated['media_path'] ?? $media->media_path,
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Media berhasil diperbarui.',
                'data'    => new MediaResource($media->load('album')),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Media Update Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal memperbarui media'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Media $media): JsonResponse
    {
        DB::beginTransaction();
        try {
            if ($media->media_path && !str_starts_with($media->media_path, 'http')) {
                Storage::disk('public')->delete($media->media_path);
            }
            $media->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Media berhasil dihapus',
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Media Delete Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal menghapus media'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}