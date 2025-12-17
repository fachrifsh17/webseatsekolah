<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jurusan;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JurusanApiController extends Controller
{
    public function index()
    {
        $data = Jurusan::orderBy('nama_jurusan')->get();
        return response()->json(['success' => true, 'data' => $data], Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function show(Jurusan $jurusan)
    {
        // Jika tidak ditemukan, Laravel akan otomatis melempar 404
        return response()->json(['success' => true, 'data' => $jurusan], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_jurusan' => 'required|string|max:100',
            'deskripsi' => 'nullable|string',
            // Asumsi 'foto' adalah path/URL, bukan file upload
            'foto' => 'nullable|string|max:255', 
        ]);
        
        $j = Jurusan::create($validated);
        return response()->json(['success' => true, 'data' => $j], Response::HTTP_CREATED);
    }

    // Menggunakan Route Model Binding
    public function update(Request $request, Jurusan $jurusan)
    {
        $validated = $request->validate([
            'nama_jurusan' => 'sometimes|required|string|max:100',
            'deskripsi' => 'nullable|string',
            'foto' => 'nullable|string|max:255',
        ]);
        
        $jurusan->update($validated);
        return response()->json(['success' => true, 'data' => $jurusan], Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function destroy(Jurusan $jurusan)
    {
        $jurusan->delete();
        return response()->json(['success' => true, 'message' => 'Jurusan berhasil dihapus.'], Response::HTTP_NO_CONTENT);
    }
}