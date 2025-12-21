<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Http\Requests\StoreSiswaRequest;
use App\Http\Requests\UpdateSiswaRequest;
use App\Http\Resources\SiswaResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Throwable;

class SiswaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Siswa::with(['user', 'kelas', 'jurusan', 'orangtua']);

        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', $request->kelas_id);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nama_lengkap', 'like', '%' . $request->search . '%')
                  ->orWhere('nis', 'like', '%' . $request->search . '%');
            });
        }

        return response()->json(SiswaResource::collection($query->latest()->paginate(20)));
    }

    public function store(StoreSiswaRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            if ($request->hasFile('foto')) {
                $validated['foto'] = $request->file('foto')->store('siswa/foto', 'public');
            }

            $siswa = Siswa::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil ditambahkan.',
                'data'    => new SiswaResource($siswa->load(['user', 'kelas', 'jurusan']))
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan siswa'
            ], 500);
        }
    }

    public function show(Siswa $siswa): JsonResponse
    {
        return response()->json(new SiswaResource($siswa->load(['user', 'kelas', 'jurusan', 'orangtua'])));
    }

    public function update(UpdateSiswaRequest $request, Siswa $siswa): JsonResponse
    {
        try {
            $validated = $request->validated();

            if ($request->hasFile('foto')) {
                if ($siswa->foto) {
                    Storage::disk('public')->delete($siswa->foto);
                }
                $validated['foto'] = $request->file('foto')->store('siswa/foto', 'public');
            }

            $siswa->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil diperbarui.',
                'data'    => new SiswaResource($siswa->load(['user', 'kelas', 'jurusan']))
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui siswa'
            ], 500);
        }
    }

    public function destroy(Siswa $siswa): JsonResponse
    {
        try {
            if ($siswa->foto) {
                Storage::disk('public')->delete($siswa->foto);
            }
            
            $siswa->delete();

            return response()->json(null, 204);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus siswa'
            ], 500);
        }
    }
}