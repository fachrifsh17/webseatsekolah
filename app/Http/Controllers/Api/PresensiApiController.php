<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Presensi;
use App\Http\Resources\PresensiResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PresensiApiController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'guru' && $user->role !== 'admin') {
            return response()->json(['message' => 'Akses hanya untuk Guru/Admin'], 403);
        }

        $query = Presensi::with(['siswa', 'tahunAjaran']);

        if ($request->has('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        }

        if ($request->has('kelas_id')) {
            $query->whereHas('siswa', function($q) use ($request) {
                $q->where('kelas_id', $request->kelas_id);
            });
        }

        return PresensiResource::collection($query->latest()->paginate(50));
    }

    public function store(Request $request)
    {
        if (Auth::user()->role !== 'guru' && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $validated = $request->validate([
            'siswa_id'        => 'required|exists:siswa,id',
            'status'          => 'required|in:Hadir,Izin,Sakit,Alfa',
            'keterangan'      => 'nullable|string',
            'tanggal'         => 'required|date',
            'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id',
        ]);

        $presensi = Presensi::updateOrCreate(
            [
                'siswa_id' => $validated['siswa_id'],
                'tanggal'  => $validated['tanggal'],
            ],
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Presensi berhasil disimpan',
            'data'    => new PresensiResource($presensi->load('siswa'))
        ], 201);
    }

    public function show($id)
    {
        $presensi = Presensi::with(['siswa', 'tahunAjaran'])->find($id);

        if (!$presensi) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        return new PresensiResource($presensi);
    }

    public function update(Request $request, $id)
    {
        if (Auth::user()->role !== 'guru' && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $presensi = Presensi::find($id);
        if (!$presensi) return response()->json(['message' => 'Data tidak ditemukan'], 404);

        $validated = $request->validate([
            'status'     => 'sometimes|required|in:Hadir,Izin,Sakit,Alfa',
            'keterangan' => 'nullable|string',
        ]);

        $presensi->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Presensi berhasil diperbarui',
            'data'    => new PresensiResource($presensi->load('siswa'))
        ]);
    }

    public function destroy($id)
    {
        if (Auth::user()->role !== 'guru' && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $presensi = Presensi::find($id);
        if (!$presensi) return response()->json(['message' => 'Data tidak ditemukan'], 404);

        $presensi->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data presensi berhasil dihapus'
        ]);
    }
}