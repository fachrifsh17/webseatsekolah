<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Http\Resources\SiswaResource;
use App\Http\Requests\StoreSiswaRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;

class SiswaApiController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        if (!Gate::allows('view', Siswa::class)) {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $query = Siswa::with(['user', 'kelas.waliKelas', 'jurusan', 'orangtua']);

        if ($user->role === 'guru') {
            $guru = $user->guru_staf; 
            if (!$guru) {
                return response()->json(['message' => 'Profil Guru tidak ditemukan'], 404);
            }
            
            $query->whereHas('kelas', function($q) use ($guru) {
                $q->where('wali_kelas_id', $guru->id);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%");
            });
        }

        return SiswaResource::collection($query->latest()->paginate(20));
    }

    public function show($id)
    {
        $user = Auth::user();
        $siswa = Siswa::with(['user', 'kelas', 'jurusan', 'orangtua'])->findOrFail($id);

        if ($user->role === 'guru') {
            if ($siswa->kelas->wali_kelas_id !== $user->guru_staf->id) {
                return response()->json(['message' => 'Akses ditolak. Siswa bukan anggota kelas Anda.'], 403);
            }
        }

        return new SiswaResource($siswa);
    }

    public function store(StoreSiswaRequest $request)
    {
        if (Gate::denies('manage', Siswa::class)) {
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

    public function update(Request $request, $id)
    {
        if (Gate::denies('manage', Siswa::class)) {
            return response()->json(['message' => 'Hanya Admin yang dapat mengubah data master siswa'], 403);
        }

        $siswa = Siswa::findOrFail($id);
        
        $validated = $request->validate([
            'user_id'        => 'sometimes|required|exists:users,id|unique:siswa,user_id,' . $siswa->id,
            'nis'            => 'sometimes|required|unique:siswa,nis,' . $siswa->id,
            'nama_lengkap'   => 'sometimes|required|string|max:100', // Sesuai varchar(100) di database
            'tempat_lahir'   => 'nullable|string|max:100', // Kolom Baru sesuai image_096627.png
            'tanggal_lahir'  => 'nullable|date',           // Kolom Baru sesuai image_096627.png
            'jenis_kelamin'  => 'sometimes|required|in:Laki-laki,Perempuan',
            'kelas_id'       => 'sometimes|required|exists:kelas,id',
            'jurusan_id'     => 'sometimes|required|exists:jurusan,id',
            'orangtua_id'    => 'nullable|exists:orangtua,id',
            'foto'           => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'no_telp_siswa'  => 'nullable|string|max:15',
            'alamat'         => 'nullable|string',
            'status_aktif'   => 'sometimes|required|in:Aktif,Lulus,Pindah,Keluar',
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
            'data' => new SiswaResource($siswa->fresh(['user', 'kelas', 'jurusan']))
        ]);
    }

    public function destroy($id)
    {
        if (Gate::denies('manage', Siswa::class)) {
            return response()->json(['message' => 'Hanya Admin yang dapat menghapus data siswa'], 403);
        }

        $siswa = Siswa::findOrFail($id);
        
        if ($siswa->foto) {
            Storage::disk('public')->delete($siswa->foto);
        }

        $siswa->delete();

        return response()->json(['success' => true, 'message' => 'Data siswa berhasil dihapus']);
    }
}