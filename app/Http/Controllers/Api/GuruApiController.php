<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\GuruStaf;
use Illuminate\Support\Facades\Validator;

class GuruApiController extends Controller
{
    public function index()
    {
        $guru = GuruStaf::with('jurusan')->orderBy('nama')->get();

        return response()->json([
            'success' => true,
            'data' => $guru
        ]);
    }

    public function show($id)
    {
        $g = GuruStaf::with('jurusan')->find($id);

        if (! $g) {
            return response()->json([
                'success' => false,
                'message' => 'Guru tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $g
        ]);
    }

    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'nip' => 'nullable|string|max:18|unique:guru_staf,nip',
            'nuptk' => 'nullable|string|max:16|unique:guru_staf,nuptk',
            'nama' => 'required|string|max:100',
            'jabatan_fungsional' => 'nullable|string|max:100',
            'status_kepegawaian' => 'nullable|string|max:50',
            'foto' => 'nullable|string|max:255',
            'jurusan_id' => 'nullable|integer|exists:jurusan,id',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $v->errors()
            ], 422);
        }

        $g = GuruStaf::create($v->validated());

        return response()->json([
            'success' => true,
            'data' => $g
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $g = GuruStaf::find($id);

        if (! $g) {
            return response()->json([
                'success' => false,
                'message' => 'Guru tidak ditemukan'
            ], 404);
        }

        $v = Validator::make($request->all(), [
            'nip' => 'nullable|string|max:18|unique:guru_staf,nip,'.$id,
            'nuptk' => 'nullable|string|max:16|unique:guru_staf,nuptk,'.$id,
            'nama' => 'sometimes|required|string|max:100',
            'jabatan_fungsional' => 'nullable|string|max:100',
            'status_kepegawaian' => 'nullable|string|max:50',
            'foto' => 'nullable|string|max:255',
            'jurusan_id' => 'nullable|integer|exists:jurusan,id',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $v->errors()
            ], 422);
        }

        $g->update($v->validated());

        return response()->json([
            'success' => true,
            'data' => $g
        ]);
    }

    public function destroy($id)
    {
        $g = GuruStaf::find($id);

        if (! $g) {
            return response()->json([
                'success' => false,
                'message' => 'Guru tidak ditemukan'
            ], 404);
        }

        $g->delete();

        return response()->json([
            'success' => true,
            'message' => 'Guru dihapus'
        ]);
    }
}
