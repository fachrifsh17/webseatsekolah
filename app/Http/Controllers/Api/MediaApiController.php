<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\Request;

class MediaApiController extends Controller
{
    public function index()
    {
        return response()->json(Media::with('album')->orderBy('id','desc')->get());
    }

    public function show($id)
    {
        $media = Media::with('album')->findOrFail($id);
        return response()->json($media);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'album_id' => 'nullable|integer|exists:album,id',
            'media_path' => 'required|string|max:255',
            'jenis_media' => 'nullable|in:Foto,Video',
            'keterangan' => 'nullable|string|max:255',
        ]);

        $media = Media::create($validated);
        return response()->json($media, 201);
    }

    public function update(Request $request, $id)
    {
        $media = Media::findOrFail($id);

        $validated = $request->validate([
            'album_id' => 'nullable|integer|exists:album,id',
            'media_path' => 'sometimes|required|string|max:255',
            'jenis_media' => 'nullable|in:Foto,Video',
            'keterangan' => 'nullable|string|max:255',
        ]);

        $media->update($validated);
        return response()->json($media);
    }

    public function destroy($id)
    {
        $media = Media::findOrFail($id);
        $media->delete();
        return response()->json(['message' => 'Media dihapus']);
    }
}
