<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Http\Resources\SiswaResource;
use App\Http\Requests\StoreSiswaRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SiswaApiController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        if ($user->role !== 'admin' && $user->role !== 'guru') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $query = Siswa::with(['user', 'kelas', 'jurusan', 'orangtua']);

        if ($request->has('kelas_id')) {
            $query->where('kelas_id', $request->kelas_id);
        }

        if ($request->has('search')) {
            $query->where('nama_lengkap', 'like', '%' . $request->search . '%')
                  ->orWhere('nis', 'like', '%' . $request->search . '%');
        }

        return SiswaResource::collection($query->latest()->paginate(20));
    }

    public function store(StoreSiswaRequest $request)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Hanya Admin yang dapat menambah siswa'], 403);
        }

        $validated = $request->validated();

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('siswa/foto', 'public');
        }

        $siswa = Siswa::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data siswa berhasil dibuat',
            'data' => new SiswaResource($siswa->load(['user', 'kelas', 'jurusan']))
        ], 201);
    }

    public function show($id)
    {
        $siswa = Siswa::with(['user', 'kelas', 'jurusan', 'orangtua'])->find($id);

        if (!$siswa) {
            return response()->json(['message' => 'Siswa tidak ditemukan'], 404);
        }

        return new SiswaResource($siswa);
    }

    public function update(Request $request, $id)
    {
        $siswa = Siswa::find($id);
        if (!$siswa) return response()->json(['message' => 'Data tidak ditemukan'], 404);

        if (Auth::user()->role !== 'admin' && Auth::user()->role !== 'guru') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $validated = $request->validate([
            'nis' => 'sometimes|required|unique:siswa,nis,' . $siswa->id,
            'nama_lengkap' => 'sometimes|required|string|max:150',
            'kelas_id' => 'sometimes|required|exists:kelas,id',
            'jurusan_id' => 'sometimes|required|exists:jurusan,id',
            'foto' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('foto')) {
            if ($siswa->foto) {
                Storage::disk('public')->delete($siswa->foto);
            }
            $validated['foto'] = $request->file('foto')->store('siswa/foto', 'public');
        }

        $siswa->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data siswa berhasil diperbarui',
            'data' => new SiswaResource($siswa->load(['user', 'kelas', 'jurusan']))
        ]);
    }

    public function destroy($id)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Hanya Admin yang dapat menghapus data siswa'], 403);
        }

        $siswa = Siswa::find($id);
        if (!$siswa) return response()->json(['message' => 'Data tidak ditemukan'], 404);

        if ($siswa->foto) {
            Storage::disk('public')->delete($siswa->foto);
        }

        $siswa->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data siswa berhasil dihapus'
        ]);
    }
}