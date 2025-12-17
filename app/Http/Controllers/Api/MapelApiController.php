<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MataPelajaran;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MapelApiController extends Controller
{
    public function index()
    {
        $mapel = MataPelajaran::with('jurusan')->orderBy('nama_mapel')->get();
        return response()->json(['success' => true, 'data' => $mapel], Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function show(MataPelajaran $mapel)
    {
        // Jika tidak ditemukan, Laravel akan otomatis melempar 404
        return response()->json(['success' => true, 'data' => $mapel], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_mapel' => 'required|string|max:100',
            'jurusan_id' => 'nullable|integer|exists:jurusan,id',
            'tipe_mapel' => 'nullable|in:umum,khusus',
            // Kategori mata pelajaran SMK
            'kategori_mapel' => 'nullable|in:normatif,adaptif,produktif',
        ]);
        
        $m = MataPelajaran::create($validated);
        return response()->json(['success' => true, 'data' => $m], Response::HTTP_CREATED);
    }

    // Menggunakan Route Model Binding
    public function update(Request $request, MataPelajaran $mapel)
    {
        $validated = $request->validate([
            'nama_mapel' => 'sometimes|required|string|max:100',
            'jurusan_id' => 'nullable|integer|exists:jurusan,id',
            'tipe_mapel' => 'nullable|in:umum,khusus',
            'kategori_mapel' => 'nullable|in:normatif,adaptif,produktif',
        ]);
        
        $mapel->update($validated);
        return response()->json(['success' => true, 'data' => $mapel], Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function destroy(MataPelajaran $mapel)
    {
        $mapel->delete();
        return response()->json(['success' => true, 'message' => 'Mata pelajaran berhasil dihapus.'], Response::HTTP_NO_CONTENT);
    }
}