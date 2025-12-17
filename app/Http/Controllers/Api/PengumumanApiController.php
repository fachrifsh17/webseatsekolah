<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pengumuman;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PengumumanApiController extends Controller
{
    public function index()
    {
        $data = Pengumuman::orderBy('tanggal_publikasi', 'desc')->get();
        return response()->json(['success' => true, 'data' => $data], Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function show(Pengumuman $pengumuman)
    {
        // Jika tidak ditemukan, Laravel akan otomatis melempar 404
        return response()->json(['success' => true, 'data' => $pengumuman], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'isi_pengumuman' => 'required|string',
            'tanggal_publikasi' => 'nullable|date',
            'penting' => 'nullable|boolean',
        ]);

        $p = Pengumuman::create($validated);
        return response()->json(['success' => true, 'data' => $p], Response::HTTP_CREATED);
    }

    // Menggunakan Route Model Binding
    public function update(Request $request, Pengumuman $pengumuman)
    {
        $validated = $request->validate([
            'judul' => 'sometimes|required|string|max:255',
            'isi_pengumuman' => 'sometimes|required|string',
            'tanggal_publikasi' => 'nullable|date',
            'penting' => 'nullable|boolean',
        ]);

        $pengumuman->update($validated);
        return response()->json(['success' => true, 'data' => $pengumuman], Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function destroy(Pengumuman $pengumuman)
    {
        $pengumuman->delete();
        return response()->json(['success' => true, 'message' => 'Pengumuman berhasil dihapus.'], Response::HTTP_NO_CONTENT);
    }
}