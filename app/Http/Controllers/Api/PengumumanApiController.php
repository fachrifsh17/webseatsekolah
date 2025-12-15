<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pengumuman;
use Illuminate\Support\Facades\Validator;

class PengumumanApiController extends Controller
{
    public function index()
    {
        $data = Pengumuman::orderBy('tanggal_publikasi', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function show($id)
    {
        $p = Pengumuman::find($id);

        if (! $p) {
            return response()->json([
                'success' => false,
                'message' => 'Pengumuman tidak ditemukan'
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
            'isi_pengumuman' => 'required|string',
            'tanggal_publikasi' => 'nullable|date',
            'penting' => 'nullable|boolean',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $v->errors()
            ], 422);
        }

        $p = Pengumuman::create($v->validated());

        return response()->json([
            'success' => true,
            'data' => $p
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $p = Pengumuman::find($id);

        if (! $p) {
            return response()->json([
                'success' => false,
                'message' => 'Pengumuman tidak ditemukan'
            ], 404);
        }

        $v = Validator::make($request->all(), [
            'judul' => 'sometimes|required|string|max:255',
            'isi_pengumuman' => 'sometimes|required|string',
            'tanggal_publikasi' => 'nullable|date',
            'penting' => 'nullable|boolean',
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
        $p = Pengumuman::find($id);

        if (! $p) {
            return response()->json([
                'success' => false,
                'message' => 'Pengumuman tidak ditemukan'
            ], 404);
        }

        $p->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pengumuman dihapus'
        ]);
    }
}
