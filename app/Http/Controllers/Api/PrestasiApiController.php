<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prestasi;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrestasiApiController extends Controller
{
    public function index()
    {
        $data = Prestasi::orderBy('tahun', 'desc')->get();
        return response()->json(['success' => true, 'data' => $data], Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function show(Prestasi $prestasi)
    {
        // Jika tidak ditemukan, Laravel akan otomatis melempar 404
        return response()->json(['success' => true, 'data' => $prestasi], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'tahun' => 'nullable|digits:4|integer',
            'tingkat' => 'nullable|string|max:50',
            'kategori' => 'nullable|in:Siswa,Sekolah',
            'foto' => 'nullable|string|max:255',
        ]);
        
        $p = Prestasi::create($validated);
        return response()->json(['success' => true, 'data' => $p], Response::HTTP_CREATED);
    }

    // Menggunakan Route Model Binding
    public function update(Request $request, Prestasi $prestasi)
    {
        $validated = $request->validate([
            'judul' => 'sometimes|required|string|max:255',
            'tahun' => 'nullable|digits:4|integer',
            'tingkat' => 'nullable|string|max:50',
            'kategori' => 'nullable|in:Siswa,Sekolah',
            'foto' => 'nullable|string|max:255',
        ]);
        
        $prestasi->update($validated);
        return response()->json(['success' => true, 'data' => $prestasi], Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function destroy(Prestasi $prestasi)
    {
        $prestasi->delete();
        return response()->json(['success' => true, 'message' => 'Prestasi berhasil dihapus.'], Response::HTTP_NO_CONTENT);
    }
}