<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Http\Resources\MediaResource;
use App\Http\Requests\StoreMediaRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class MediaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth.token'),
            new Middleware('role:Admin,SuperAdmin'),
            new Middleware('log.admin', only: ['store', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $albumId = $request->query('album_id');
        $query = Media::with('album');

        if ($albumId) {
            $query->where('album_id', $albumId);
        }
        
        $data = $query->latest()->paginate(20);
        return MediaResource::collection($data);
    }
    
    public function show(Media $media)
    {
        $media->load('album');
        return new MediaResource($media);
    }

    public function store(StoreMediaRequest $request)
    {
        $validated = $request->validated();

        $path = $request->file('media_file')->store('uploads/media', 'public');

        $media = Media::create([
            'album_id' => $validated['album_id'],
            'media_path' => $path,
            'jenis_media' => $validated['jenis_media'],
            'keterangan' => $validated['keterangan'] ?? null,
        ]);
        
        return new MediaResource($media->load('album'));
    }

    public function destroy(Media $media)
    {
        if ($media->media_path) {
            Storage::disk('public')->delete($media->media_path);
        }

        $media->delete();
        return response()->json(null, 204);
    }
}