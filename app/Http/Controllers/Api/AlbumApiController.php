<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Album;
use Illuminate\Support\Facades\Validator;

class AlbumApiController extends Controller
{
    public function index()
    {
        $albums = Album::orderBy('tanggal_kegiatan', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $albums
        ]);
    }

    public function show($id)
    {
        $album = Album::with('media')->find($id);

        if (! $album) {
            return response()->json([
                'success' => false,
                'message' => 'Album tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $album
        ]);
    }

    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'nama_album' => 'required|string|max:255',
            'tanggal_kegiatan' => 'nullable|date',
            'cover_path' => 'nullable|string|max:255',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $v->errors()
            ], 422);
        }

        $album = Album::create($v->validated());

        return response()->json([
            'success' => true,
            'data' => $album
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $album = Album::find($id);

        if (! $album) {
            return response()->json([
                'success' => false,
                'message' => 'Album tidak ditemukan'
            ], 404);
        }

        $v = Validator::make($request->all(), [
            'nama_album' => 'sometimes|required|string|max:255',
            'tanggal_kegiatan' => 'nullable|date',
            'cover_path' => 'nullable|string|max:255',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $v->errors()
            ], 422);
        }

        $album->update($v->validated());

        return response()->json([
            'success' => true,
            'data' => $album
        ]);
    }

    public function destroy($id)
    {
        $album = Album::find($id);

        if (! $album) {
            return response()->json([
                'success' => false,
                'message' => 'Album tidak ditemukan'
            ], 404);
        }

        $album->delete();

        return response()->json([
            'success' => true,
            'message' => 'Album dihapus'
        ]);
    }
}
