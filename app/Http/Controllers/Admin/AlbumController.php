<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Http\Resources\AlbumResource;
use App\Http\Requests\StoreAlbumRequest;
use App\Http\Requests\UpdateAlbumRequest;
use Illuminate\Support\Facades\Storage;

class AlbumController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin,Guru');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
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

    public function update(UpdateAlbumRequest $request, Album $album)
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

        return response()->noContent();
    }
}