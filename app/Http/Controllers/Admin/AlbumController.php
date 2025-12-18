<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Http\Resources\AlbumResource;
use App\Http\Requests\StoreAlbumRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AlbumController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth.token'),
            new Middleware('role:Admin,SuperAdmin'),
            new Middleware('log.admin', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index()
    {
        $data = Album::orderByDesc('tanggal_kegiatan')->paginate(12);
        return AlbumResource::collection($data);
    }

    public function show(Album $album)
    {
        return new AlbumResource($album);
    }

    public function store(StoreAlbumRequest $request)
    {
        $validated = $request->validated();

        if ($request->hasFile('cover')) {
            $validated['cover_path'] = $request->file('cover')->store('uploads/album', 'public');
        }

        $album = Album::create($validated);

        return new AlbumResource($album);
    }

    public function update(StoreAlbumRequest $request, Album $album)
    {
        $validated = $request->validated();

        if ($request->hasFile('cover')) {
            if ($album->cover_path) {
                Storage::disk('public')->delete($album->cover_path);
            }
            $validated['cover_path'] = $request->file('cover')->store('uploads/album', 'public');
        }

        $album->update($validated);

        return new AlbumResource($album);
    }

    public function destroy(Album $album)
    {
        if ($album->cover_path) {
            Storage::disk('public')->delete($album->cover_path);
        }

        $album->delete();

        return response()->json(null, 204);
    }
}