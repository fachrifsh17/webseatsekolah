<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StrukturJabatan;
use Illuminate\Http\Request;

class StrukturApiController extends Controller
{
    public function index()
    {
        return response()->json(StrukturJabatan::with('guru')->orderBy('urutan_tampil')->get());
    }

    public function show($id)
    {
        $s = StrukturJabatan::with('guru')->findOrFail($id);
        return response()->json($s);
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
        return response()->json($s, 201);
    }

    public function update(Request $request, $id)
    {
        $s = StrukturJabatan::findOrFail($id);

        $validated = $request->validate([
            'guru_staf_id' => 'nullable|integer|exists:guru_staf,id',
            'nama_jabatan_struktural' => 'nullable|string|max:100',
            'periode_mulai' => 'nullable|date',
            'urutan_tampil' => 'nullable|integer',
        ]);

        $s->update($validated);
        return response()->json($s);
    }

    public function destroy($id)
    {
        $s = StrukturJabatan::findOrFail($id);
        $s->delete();
        return response()->json(['message' => 'Struktur jabatan dihapus']);
    }
}
