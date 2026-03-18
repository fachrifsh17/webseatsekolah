<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Http\Resources\MediaResource;
use App\Http\Requests\StoreMediaRequest;
use App\Http\Requests\UpdateMediaRequest;
use Illuminate\Http\Request;
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
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy', 'massDestroy']);
        
        $this->authorizeResource(Media::class, 'media', [
            'except' => ['massDestroy']
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $albumId = $request->query('album_id');
            $perPage = min((int) $request->query('per_page', 20), 100);

            $query = Media::query();
            
            if ($albumId) {
                $query->where('album_id', $albumId);
            } else {
                $query->with('album');
            }

            $data = $query->latest()->paginate($perPage);
            $paginationData = $data->toArray();

            return response()->json([
                'success' => true,
                'data'    => MediaResource::collection($data),
                'meta'    => [
                    'current_page'  => $data->currentPage(),
                    'last_page'     => $data->lastPage(),
                    'per_page'      => $data->perPage(),
                    'total'         => $data->total(),
                    'from'          => $data->firstItem(),
                    'to'            => $data->lastItem(),
                    'next_page_url' => $data->nextPageUrl(),
                    'prev_page_url' => $data->previousPageUrl(),
                    'path'          => $paginationData['path'],
                    'links'         => $paginationData['links'],
                    'filter_album'  => $albumId ?? 'Semua'
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
        $targetPath = public_path('uploads/media');
        $insertedIds = [];

        DB::beginTransaction();
        try {
            if ($jenis === 'foto' && $request->hasFile('media')) {
                $files = is_array($request->file('media')) ? $request->file('media') : [$request->file('media')];
                foreach ($files as $file) {
                    if ($file->isValid()) {
                        $fileName = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                        $file->move($targetPath, $fileName);
                        
                        $media = Media::create([
                            'album_id'    => $validated['album_id'],
                            'media_path'  => $fileName,
                            'jenis_media' => 'Foto',
                            'keterangan'  => $validated['keterangan'] ?? null,
                        ]);
                        $insertedIds[] = $media->id;
                    }
                }
            } else {
                $path = $validated['media_path'] ?? null;
                if ($request->hasFile('media') && $request->file('media')->isValid()) {
                    $file = $request->file('media');
                    $fileName = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                    $file->move($targetPath, $fileName);
                    $path = $fileName;
                }

                $media = Media::create([
                    'album_id'    => $validated['album_id'],
                    'media_path'  => $path,
                    'jenis_media' => ucfirst($jenis),
                    'keterangan'  => $validated['keterangan'] ?? null,
                ]);
                $insertedIds[] = $media->id;
            }

            DB::commit();

            $resultData = Media::with('album')->whereIn('id', $insertedIds)->get();

            return response()->json([
                'success' => true,
                'message' => count($insertedIds) . ' Media berhasil ditambahkan.',
                'data'    => MediaResource::collection($resultData),
            ], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Media Store Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal menyimpan media',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateMediaRequest $request, Media $media): JsonResponse
    {
        $validated = $request->validated();
        $targetPath = public_path('uploads/media');
        $oldPath = $media->media_path;

        DB::beginTransaction();
        try {
            if ($request->hasFile('media') && $request->file('media')->isValid()) {
                if ($oldPath) {
                    $oldFileName = basename($oldPath);
                    $fullOldPath = $targetPath . '/' . $oldFileName;
                    if (file_exists($fullOldPath)) unlink($fullOldPath);
                }
                
                $file = $request->file('media');
                $fileName = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                $file->move($targetPath, $fileName);
                $validated['media_path'] = $fileName;
            }

            $media->update([
                'album_id'    => $validated['album_id'] ?? $media->album_id,
                'jenis_media' => isset($validated['jenis_media']) ? ucfirst($validated['jenis_media']) : $media->jenis_media,
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
                'message' => 'Gagal memperbarui media',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Media $media): JsonResponse
    {
        DB::beginTransaction();
        try {
            if ($media->media_path) {
                $fileName = basename($media->media_path);
                $filePath = public_path('uploads/media/' . $fileName);
                if (file_exists($filePath)) unlink($filePath);
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

    public function massDestroy(Request $request): JsonResponse
    {
        $this->authorize('deleteAny', Media::class);

        $ids = $request->input('ids');

        if (!$ids || !is_array($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'Pilih data media yang ingin dihapus terlebih dahulu.'
            ], Response::HTTP_BAD_REQUEST);
        }

        DB::beginTransaction();
        try {
            $mediaItems = Media::whereIn('id', $ids)->get();
            $count = 0;

            foreach ($mediaItems as $item) {
                /** @var Media $item */
                if ($item->media_path) {
                    $fileName = basename($item->media_path);
                    $filePath = public_path('uploads/media/' . $fileName);
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
                $item->delete();
                $count++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $count . ' Media berhasil dihapus secara massal.',
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Media Mass Delete Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data secara massal',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}