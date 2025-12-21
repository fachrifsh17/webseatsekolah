<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Http\Resources\MediaResource;
use App\Http\Requests\StoreMediaRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MediaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin,Guru');
        $this->middleware('log.admin')->only(['store', 'destroy']);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $albumId = $request->query('album_id');
        $query = Media::with('album');

        if ($albumId) {
            $query->where('album_id', $albumId);
        }

        $data = $query->latest()->paginate(20);
        return MediaResource::collection($data);
    }

    public function show(Media $media): MediaResource
    {
        $media->load('album');
        return new MediaResource($media);
    }

    public function store(StoreMediaRequest $request): MediaResource
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $path = $request->file('media_file')->store('uploads/media', 'public');

            $media = Media::create([
                'album_id'    => $validated['album_id'],
                'media_path'  => $path,
                'jenis_media' => $validated['jenis_media'],
                'keterangan'  => $validated['keterangan'] ?? null,
            ]);

            DB::commit();
            return new MediaResource($media->load('album'));
        } catch (\Throwable $e) {
            DB::rollBack();
            // hapus file jika sudah ter-upload tapi gagal menyimpan DB
            if (!empty($path ?? null)) {
                Storage::disk('public')->delete($path);
            }
            abort(500, 'Gagal menyimpan media: ' . $e->getMessage());
        }
    }

    public function destroy(Media $media): JsonResponse
    {
        DB::beginTransaction();
        try {
            if ($media->media_path) {
                Storage::disk('public')->delete($media->media_path);
            }

            $media->delete();
            DB::commit();

            return response()->json(null, 204);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus media',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
