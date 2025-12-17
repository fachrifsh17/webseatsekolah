<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Http\Resources\MediaResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum'); 
        $this->middleware('role:Admin');
    }

    public function index(Request $request)
    {
        // Filter berdasarkan album_id dari query string
        $albumId = $request->query('album_id');
        
        $query = Media::with('album');

        if ($albumId) {
            $query->where('album_id', $albumId);
        }
        
        $data = $query->latest()->paginate(20);
        
        return MediaResource::collection($data);
    }
    
    public function show($id)
    {
        $item = Media::with('album')->findOrFail($id);
        
        return new MediaResource($item);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'album_id' => 'required|exists:albums,id',
            'media_file' => 'required|file|mimes:jpg,jpeg,png,mp4,mov|max:50000',
            'jenis_media' => 'required|in:Foto,Video',
            'keterangan' => 'nullable|string|max:255',
        ]);

        $path = $request->file('media_file')->store('uploads/media','public');

        $media = Media::create([
            'album_id' => $validated['album_id'],
            'media_path' => $path,
            'jenis_media' => $validated['jenis_media'],
            'keterangan' => $validated['keterangan'] ?? null,
        ]);
        
        return new MediaResource($media->load('album'));
    }

    // Catatan: Endpoint update tidak ada di kode awal Anda. Biasanya media tidak di-update, hanya dihapus dan dibuat baru.

    public function destroy(Media $media)
    {
        if ($media->media_path) {
            Storage::disk('public')->delete($media->media_path);
        }

        $media->delete();
        
        return response()->json(null, 204);
    }
}