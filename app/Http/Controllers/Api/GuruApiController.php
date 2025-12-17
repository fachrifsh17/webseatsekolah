<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GuruStaf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GuruApiController extends Controller
{
    public function index()
    {
        $guru = GuruStaf::with('jurusan')->orderBy('nama')->get();
        return response()->json(['success' => true, 'data' => $guru], Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function show(GuruStaf $guru)
    {
        $guru->load('jurusan');
        return response()->json(['success' => true, 'data' => $guru], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            // Validasi unik NIP/NUPTK tanpa mengabaikan ID (karena ini store/create)
            'nip' => 'nullable|string|max:18|unique:guru_staf,nip',
            'nuptk' => 'nullable|string|max:16|unique:guru_staf,nuptk',
            'nama' => 'required|string|max:100',
            'jabatan_fungsional' => 'nullable|string|max:100',
            'status_kepegawaian' => 'nullable|string|max:50',
            'foto' => 'nullable|string|max:255',
            'jurusan_id' => 'nullable|integer|exists:jurusan,id',
        ]);
        
        $g = GuruStaf::create($validated);
        return response()->json(['success' => true, 'data' => $g], Response::HTTP_CREATED);
    }

    // Menggunakan Route Model Binding
    public function update(Request $request, GuruStaf $guru)
    {
        // Mendapatkan ID model untuk validasi unik (mengabaikan ID saat ini)
        $id = $guru->id;

        $validated = $request->validate([
            'nip' => 'nullable|string|max:18|unique:guru_staf,nip,' . $id,
            'nuptk' => 'nullable|string|max:16|unique:guru_staf,nuptk,' . $id,
            'nama' => 'sometimes|required|string|max:100',
            'jabatan_fungsional' => 'nullable|string|max:100',
            'status_kepegawaian' => 'nullable|string|max:50',
            'foto' => 'nullable|string|max:255',
            'jurusan_id' => 'nullable|integer|exists:jurusan,id',
        ]);
        
        $guru->update($validated);
        return response()->json(['success' => true, 'data' => $guru], Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function destroy(GuruStaf $guru)
    {
        $guru->delete();
        return response()->json(['success' => true, 'message' => 'Data Guru berhasil dihapus.'], Response::HTTP_NO_CONTENT);
    }
}