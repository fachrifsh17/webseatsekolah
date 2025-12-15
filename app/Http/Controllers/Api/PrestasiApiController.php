<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Prestasi;
use Illuminate\Support\Facades\Validator;

class PrestasiApiController extends Controller
{
    public function index()
    {
        $data = Prestasi::orderBy('tahun', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function show($id)
    {
        $p = Prestasi::find($id);

        if (! $p) {
            return response()->json([
                'success' => false,
                'message' => 'Prestasi tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $p
        ]);
    }

    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'judul' => 'required|string|max:255',
            'tahun' => 'nullable|digits:4|integer',
            'tingkat' => 'nullable|string|max:50',
            'kategori' => 'nullable|in:Siswa,Sekolah',
            'foto' => 'nullable|string|max:255',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $v->errors()
            ], 422);
        }

        $p = Prestasi::create($v->validated());

        return response()->json([
            'success' => true,
            'data' => $p
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $p = Prestasi::find($id);

        if (! $p) {
            return response()->json([
                'success' => false,
                'message' => 'Prestasi tidak ditemukan'
            ], 404);
        }

        $v = Validator::make($request->all(), [
            'judul' => 'sometimes|required|string|max:255',
            'tahun' => 'nullable|digits:4|integer',
            'tingkat' => 'nullable|string|max:50',
            'kategori' => 'nullable|in:Siswa,Sekolah',
            'foto' => 'nullable|string|max:255',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $v->errors()
            ], 422);
        }

        $p->update($v->validated());

        return response()->json([
            'success' => true,
            'data' => $p
        ]);
    }

    public function destroy($id)
    {
        $p = Prestasi::find($id);

        if (! $p) {
            return response()->json([
                'success' => false,
                'message' => 'Prestasi tidak ditemukan'
            ], 404);
        }

        $p->delete();

        return response()->json([
            'success' => true,
            'message' => 'Prestasi dihapus'
        ]);
    }
}
