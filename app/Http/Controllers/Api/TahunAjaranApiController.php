<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TahunAjaran;
use App\Http\Resources\TahunAjaranResource;
use App\Http\Requests\StoreTahunAjaranRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TahunAjaranApiController extends Controller
{
    public function index()
    {
        $tahunAjaran = TahunAjaran::orderBy('nama', 'desc')->get();
        return TahunAjaranResource::collection($tahunAjaran);
    }

    public function store(StoreTahunAjaranRequest $request)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Hanya Admin yang dapat menambah tahun ajaran'], 403);
        }

        if ($request->aktif) {
            TahunAjaran::where('aktif', true)->update(['aktif' => false]);
        }

        $tahunAjaran = TahunAjaran::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Tahun ajaran berhasil ditambahkan',
            'data'    => new TahunAjaranResource($tahunAjaran)
        ], 201);
    }

    public function show($id)
    {
        $tahunAjaran = TahunAjaran::find($id);
        if (!$tahunAjaran) return response()->json(['message' => 'Data tidak ditemukan'], 404);

        return new TahunAjaranResource($tahunAjaran);
    }

    public function update(Request $request, $id)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Hanya Admin yang dapat mengubah tahun ajaran'], 403);
        }

        $tahunAjaran = TahunAjaran::find($id);
        if (!$tahunAjaran) return response()->json(['message' => 'Data tidak ditemukan'], 404);

        $validated = $request->validate([
            'nama' => 'sometimes|required|string|max:50',
            'aktif' => 'sometimes|required|boolean'
        ]);

        if (isset($validated['aktif']) && $validated['aktif'] == true) {
            TahunAjaran::where('id', '!=', $id)->update(['aktif' => false]);
        }

        $tahunAjaran->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tahun ajaran berhasil diperbarui',
            'data'    => new TahunAjaranResource($tahunAjaran)
        ]);
    }

    public function destroy($id)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Hanya Admin yang dapat menghapus tahun ajaran'], 403);
        }

        $tahunAjaran = TahunAjaran::find($id);
        if (!$tahunAjaran) return response()->json(['message' => 'Data tidak ditemukan'], 404);

        if ($tahunAjaran->kelas()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tahun ajaran tidak bisa dihapus karena sudah memiliki data kelas'
            ], 422);
        }

        $tahunAjaran->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tahun ajaran berhasil dihapus'
        ]);
    }
}