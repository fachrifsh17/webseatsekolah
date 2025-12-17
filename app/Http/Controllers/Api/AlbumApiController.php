<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Album;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AlbumApiController extends Controller
{
    // Mengambil semua album
    public function index()
    {
        $albums = Album::orderBy('tanggal_kegiatan', 'desc')->get();
        return response()->json(['success' => true, 'data' => $albums], Response::HTTP_OK);
    }

    // Menampilkan detail satu album (menggunakan Route Model Binding)
    public function show(Album $album)
    {
        // Jika album tidak ditemukan, Laravel akan otomatis melempar 404
        $album->load('media');
        return response()->json(['success' => true, 'data' => $album], Response::HTTP_OK);
    }

    // Menyimpan album baru
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_album' => 'required|string|max:255',
            'tanggal_kegiatan' => 'nullable|date',
            'cover_path' => 'nullable|string|max:255',
        ]);
        
        $album = Album::create($validated);
        return response()->json(['success' => true, 'data' => $album], Response::HTTP_CREATED);
    }

    // Memperbarui album yang sudah ada (menggunakan Route Model Binding)
    public function update(Request $request, Album $album)
    {
        $validated = $request->validate([
            'nama_album' => 'sometimes|required|string|max:255',
            'tanggal_kegiatan' => 'nullable|date',
            'cover_path' => 'nullable|string|max:255',
        ]);
        
        $album->update($validated);
        return response()->json(['success' => true, 'data' => $album], Response::HTTP_OK);
    }

    // Menghapus album (menggunakan Route Model Binding)
    public function destroy(Album $album)
    {
        $album->delete();
        return response()->json(['success' => true, 'message' => 'Album berhasil dihapus.'], Response::HTTP_NO_CONTENT);
    }
}