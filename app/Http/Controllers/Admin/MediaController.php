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
use Throwable;

class MediaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin,Guru');
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

    public function store(StoreMediaRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $path = null;

        DB::beginTransaction();
        try {
            $jenis = strtolower($validated['jenis_media']);

            if ($jenis === 'foto' && $request->hasFile('media') && $request->file('media')->isValid()) {
                $path = $request->file('media')->store('uploads/media', 'public');
                $validated['media_path'] = $path;
            }

            if ($jenis === 'video') {
                $validated['media_path'] = $validated['media_path'] ?? null;
            }

            $media = Media::create([
                'album_id'    => $validated['album_id'],
                'media_path'  => $validated['media_path'] ?? null,
                'jenis_media' => $validated['jenis_media'],
                'keterangan'  => $validated['keterangan'] ?? null,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Media berhasil ditambahkan.',
                'data'    => new MediaResource($media->load('album')),
            ], 201);
        } catch (Throwable $e) {
            DB::rollBack();

            if (!empty($path) && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan media.',
            ], 500);
        }
    }

    public function update(Request $request, Media $media): JsonResponse
    {
        $rules = [
            'album_id'    => ['sometimes', 'integer', 'exists:albums,id'],
            'jenis_media' => ['sometimes', 'string', 'in:foto,video'],
            'keterangan'  => ['sometimes', 'nullable', 'string'],
            'media'       => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:51200'],
        ];

        $validated = $request->validate($rules);
        $newPath = null;
        $originalPath = $media->getOriginal('media_path');

        DB::beginTransaction();
        try {
            if ($request->hasFile('media') && $request->file('media')->isValid()) {
                $newPath = $request->file('media')->store('uploads/media', 'public');
                $validated['media_path'] = $newPath;
            }

            $media->fill($validated);
            $media->save();

            if ($newPath && $originalPath && Storage::disk('public')->exists($originalPath)) {
                Storage::disk('public')->delete($originalPath);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Media berhasil diperbarui.',
                'data'    => new MediaResource($media->load('album')),
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            if ($newPath && Storage::disk('public')->exists($newPath)) {
                Storage::disk('public')->delete($newPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui media.',
            ], 500);
        }
    }

    public function destroy(Media $media): JsonResponse
    {
        DB::beginTransaction();
        try {
            if ($media->media_path && Storage::disk('public')->exists($media->media_path)) {
                Storage::disk('public')->delete($media->media_path);
            }

            $media->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Media berhasil dihapus.'
            ], 200);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus media.',
            ], 500);
        }
    }
}
