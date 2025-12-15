<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MataPelajaran;
use Illuminate\Support\Facades\Validator;

class MapelApiController extends Controller
{
    public function index()
    {
        $mapel = MataPelajaran::with('jurusan')
            ->orderBy('nama_mapel')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $mapel
        ]);
    }

    public function show($id)
    {
        $m = MataPelajaran::find($id);

        if (! $m) {
            return response()->json([
                'success' => false,
                'message' => 'Mata pelajaran tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $m
        ]);
    }

    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'nama_mapel' => 'required|string|max:100',
            'jurusan_id' => 'nullable|integer|exists:jurusan,id',
            'tipe_mapel' => 'nullable|in:umum,khusus',
            'kategori_mapel' => 'nullable|in:normatif,adaptif,produktif',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $v->errors()
            ], 422);
        }

        $m = MataPelajaran::create($v->validated());

        return response()->json([
            'success' => true,
            'data' => $m
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $m = MataPelajaran::find($id);

        if (! $m) {
            return response()->json([
                'success' => false,
                'message' => 'Mata pelajaran tidak ditemukan'
            ], 404);
        }

        $v = Validator::make($request->all(), [
            'nama_mapel' => 'sometimes|required|string|max:100',
            'jurusan_id' => 'nullable|integer|exists:jurusan,id',
            'tipe_mapel' => 'nullable|in:umum,khusus',
            'kategori_mapel' => 'nullable|in:normatif,adaptif,produktif',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $v->errors()
            ], 422);
        }

        $m->update($v->validated());

        return response()->json([
            'success' => true,
            'data' => $m
        ]);
    }

    public function destroy($id)
    {
        $m = MataPelajaran::find($id);

        if (! $m) {
            return response()->json([
                'success' => false,
                'message' => 'Mata pelajaran tidak ditemukan'
            ], 404);
        }

        $m->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mata pelajaran dihapus'
        ]);
    }
}
