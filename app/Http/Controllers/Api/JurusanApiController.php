<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Jurusan;
use Illuminate\Support\Facades\Validator;

class JurusanApiController extends Controller
{
    public function index()
    {
        $data = Jurusan::orderBy('nama_jurusan')->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function show($id)
    {
        $item = Jurusan::find($id);

        if (! $item) {
            return response()->json([
                'success' => false,
                'message' => 'Jurusan tidak ditemukan'
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
            'nama_jurusan' => 'required|string|max:100',
            'deskripsi' => 'nullable|string',
            'foto' => 'nullable|string|max:255',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $v->errors()
            ], 422);
        }

        $j = Jurusan::create($v->validated());

        return response()->json([
            'success' => true,
            'data' => $j
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $j = Jurusan::find($id);

        if (! $j) {
            return response()->json([
                'success' => false,
                'message' => 'Jurusan tidak ditemukan'
            ], 404);
        }

        $v = Validator::make($request->all(), [
            'nama_jurusan' => 'sometimes|required|string|max:100',
            'deskripsi' => 'nullable|string',
            'foto' => 'nullable|string|max:255',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $v->errors()
            ], 422);
        }

        $j->update($v->validated());

        return response()->json([
            'success' => true,
            'data' => $j
        ]);
    }

    public function destroy($id)
    {
        $j = Jurusan::find($id);

        if (! $j) {
            return response()->json([
                'success' => false,
                'message' => 'Jurusan tidak ditemukan'
            ], 404);
        }

        $j->delete();

        return response()->json([
            'success' => true,
            'message' => 'Jurusan dihapus'
        ]);
    }
}
