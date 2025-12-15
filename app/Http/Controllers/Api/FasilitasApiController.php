<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Fasilitas;
use Illuminate\Support\Facades\Validator;

class FasilitasApiController extends Controller
{
    public function index()
    {
        $data = Fasilitas::orderBy('id','desc')->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function show($id)
    {
        $item = Fasilitas::find($id);

        if (! $item) {
            return response()->json([
                'success' => false,
                'message' => 'Fasilitas tidak ditemukan'
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
            'nama_fasilitas' => 'required|string|max:150',
            'foto' => 'nullable|string|max:255',
            'keterangan' => 'nullable|string|max:255',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $v->errors()
            ], 422);
        }

        $f = Fasilitas::create($v->validated());

        return response()->json([
            'success' => true,
            'data' => $f
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $f = Fasilitas::find($id);

        if (! $f) {
            return response()->json([
                'success' => false,
                'message' => 'Fasilitas tidak ditemukan'
            ], 404);
        }

        $v = Validator::make($request->all(), [
            'nama_fasilitas' => 'sometimes|required|string|max:150',
            'foto' => 'nullable|string|max:255',
            'keterangan' => 'nullable|string|max:255',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $v->errors()
            ], 422);
        }

        $f->update($v->validated());

        return response()->json([
            'success' => true,
            'data' => $f
        ]);
    }

    public function destroy($id)
    {
        $f = Fasilitas::find($id);

        if (! $f) {
            return response()->json([
                'success' => false,
                'message' => 'Fasilitas tidak ditemukan'
            ], 404);
        }

        $f->delete();

        return response()->json([
            'success' => true,
            'message' => 'Fasilitas dihapus'
        ]);
    }
}
