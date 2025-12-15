<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Berita;
use Illuminate\Support\Facades\Validator;

class BeritaApiController extends Controller
{
    public function index()
    {
        $berita = Berita::orderBy('tanggal_publikasi','desc')->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $berita
        ]);
    }

    public function show($id)
    {
        $item = Berita::find($id);

        if (! $item) {
            return response()->json([
                'success' => false,
                'message' => 'Berita tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $item
        ]);
    }

    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'judul' => 'required|string|max:255',
            'isi_berita' => 'required|string',
            'tanggal_publikasi' => 'nullable|date',
            'foto' => 'nullable|string|max:255',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $v->errors()
            ], 422);
        }

        $berita = Berita::create($v->validated());

        return response()->json([
            'success' => true,
            'data' => $berita
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $berita = Berita::find($id);

        if (! $berita) {
            return response()->json([
                'success' => false,
                'message' => 'Berita tidak ditemukan'
            ], 404);
        }

        $v = Validator::make($request->all(), [
            'judul' => 'sometimes|required|string|max:255',
            'isi_berita' => 'sometimes|required|string',
            'tanggal_publikasi' => 'nullable|date',
            'foto' => 'nullable|string|max:255',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $v->errors()
            ], 422);
        }

        $berita->update($v->validated());

        return response()->json([
            'success' => true,
            'data' => $berita
        ]);
    }

    public function destroy($id)
    {
        $berita = Berita::find($id);

        if (! $berita) {
            return response()->json([
                'success' => false,
                'message' => 'Berita tidak ditemukan'
            ], 404);
        }

        $berita->delete();

        return response()->json([
            'success' => true,
            'message' => 'Berita dihapus'
        ]);
    }
}
