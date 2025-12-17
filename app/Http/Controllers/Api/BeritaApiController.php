<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Berita;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BeritaApiController extends Controller
{
    public function index()
    {
        $berita = Berita::orderBy('tanggal_publikasi', 'desc')->paginate(10);
        return response()->json(['success' => true, 'data' => $berita], Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function show(Berita $berita)
    {
        // Berita otomatis ditemukan, jika tidak ada akan melempar 404
        return response()->json(['success' => true, 'data' => $berita], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'isi_berita' => 'required|string',
            'tanggal_publikasi' => 'nullable|date',
            // Asumsi 'foto' adalah path/URL, bukan file upload
            'foto' => 'nullable|string|max:255', 
        ]);
        
        $berita = Berita::create($validated);
        return response()->json(['success' => true, 'data' => $berita], Response::HTTP_CREATED);
    }

    // Menggunakan Route Model Binding
    public function update(Request $request, Berita $berita)
    {
        $validated = $request->validate([
            'judul' => 'sometimes|required|string|max:255',
            'isi_berita' => 'sometimes|required|string',
            'tanggal_publikasi' => 'nullable|date',
            'foto' => 'nullable|string|max:255',
        ]);
        
        $berita->update($validated);
        return response()->json(['success' => true, 'data' => $berita], Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function destroy(Berita $berita)
    {
        $berita->delete();
        return response()->json(['success' => true, 'message' => 'Berita berhasil dihapus.'], Response::HTTP_NO_CONTENT);
    }
}