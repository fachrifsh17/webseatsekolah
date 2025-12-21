<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PoinSiswa;
use App\Http\Resources\PoinSiswaResource;
use App\Http\Requests\StorePoinSiswaRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PoinSiswaApiController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        $query = PoinSiswa::with(['siswa', 'guru', 'tahunAjaran']);

        if ($user->role === 'siswa') {
            $query->where('siswa_id', optional($user->siswa)->id);
        } elseif ($user->role === 'orangtua') {
            $siswaIds = optional($user->orangtua)->siswa ? $user->orangtua->siswa->pluck('id') : [];
            $query->whereIn('siswa_id', $siswaIds);
        }

        return PoinSiswaResource::collection($query->latest()->paginate(20));
    }

    public function store(StorePoinSiswaRequest $request)
    {
        if (Auth::user()->role !== 'guru' && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $poin = PoinSiswa::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Poin dicatat',
            'data' => new PoinSiswaResource($poin->load(['siswa', 'guru']))
        ], 201);
    }

    public function show($id)
    {
        $poinSiswa = PoinSiswa::with(['siswa', 'guru', 'tahunAjaran'])->find($id);
        
        if (!$poinSiswa) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        return new PoinSiswaResource($poinSiswa);
    }

    public function update(Request $request, $id)
    {
        $poinSiswa = PoinSiswa::find($id);
        if (!$poinSiswa) return response()->json(['message' => 'Data tidak ditemukan'], 404);

        if (Auth::user()->role !== 'guru' && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $validated = $request->validate([
            'indikator' => 'sometimes|required|string',
            'poin_positif' => 'nullable|integer',
            'poin_negatif' => 'nullable|integer',
        ]);

        $poinSiswa->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil diperbarui',
            'data' => new PoinSiswaResource($poinSiswa->load(['siswa', 'guru']))
        ]);
    }

    public function destroy($id)
    {
        $poinSiswa = PoinSiswa::find($id);
        if (!$poinSiswa) return response()->json(['message' => 'Data tidak ditemukan'], 404);

        if (Auth::user()->role !== 'guru' && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $poinSiswa->delete();
        return response()->json(['success' => true, 'message' => 'Berhasil dihapus']);
    }
}