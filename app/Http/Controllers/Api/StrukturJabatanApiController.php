<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StrukturJabatan;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StrukturJabatanApiController extends Controller
{
    public function index()
    {
        $data = StrukturJabatan::with('guru')->orderBy('urutan_tampil')->get();
        return response()->json($data, Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function show(StrukturJabatan $strukturJabatan)
    {
        $strukturJabatan->load('guru');
        // Jika tidak ditemukan, Laravel akan otomatis melempar 404
        return response()->json($strukturJabatan, Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'guru_staf_id' => 'nullable|integer|exists:guru_staf,id',
            'nama_jabatan_struktural' => 'nullable|string|max:100',
            'periode_mulai' => 'nullable|date',
            'urutan_tampil' => 'nullable|integer',
        ]);

        $s = StrukturJabatan::create($validated);
        return response()->json($s, Response::HTTP_CREATED);
    }

    // Menggunakan Route Model Binding
    public function update(Request $request, StrukturJabatan $strukturJabatan)
    {
        $validated = $request->validate([
            'guru_staf_id' => 'nullable|integer|exists:guru_staf,id',
            'nama_jabatan_struktural' => 'nullable|string|max:100',
            'periode_mulai' => 'nullable|date',
            'urutan_tampil' => 'nullable|integer',
        ]);

        $strukturJabatan->update($validated);
        return response()->json($strukturJabatan, Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function destroy(StrukturJabatan $strukturJabatan)
    {
        $strukturJabatan->delete();
        return response()->json(['message' => 'Struktur jabatan berhasil dihapus.'], Response::HTTP_NO_CONTENT);
    }
}