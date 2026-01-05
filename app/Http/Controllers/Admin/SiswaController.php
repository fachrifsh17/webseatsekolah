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
use Illuminate\Support\Facades\DB;
use Throwable;

class SiswaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

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
        $validated = $request->validated();

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('siswa/foto', 'public');
        }

        foreach (['nis','user_id'] as $field) {
            if (!empty($validated[$field]) && Siswa::where($field, $validated[$field])->exists()) {
                if (!empty($validated['foto'])) {
                    Storage::disk('public')->delete($validated['foto']);
                }
                return response()->json([
                    'success' => false,
                    'message' => strtoupper($field).' sudah terdaftar.',
                    'errors'  => [$field => [strtoupper($field).' sudah terdaftar.']]
                ], 409);
            }
        }

        try {
            $siswa = DB::transaction(fn() => Siswa::create($validated));
            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil ditambahkan.',
                'data'    => new SiswaResource($siswa->load(['user','kelas','jurusan','orangtua']))
            ], 201);
        } catch (Throwable $e) {
            if (!empty($validated['foto'] ?? null)) {
                Storage::disk('public')->delete($validated['foto']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan siswa',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], 500);
        }
    }

    public function show(?Siswa $siswa): JsonResponse
    {
        if (!$siswa) {
            return response()->json([
                'success' => false,
                'message' => 'Data siswa tidak ditemukan',
                'errors'  => ['id' => ['Siswa dengan ID tersebut tidak ada']]
            ], 404);
        }

        return response()->json(new SiswaResource($siswa->load(['user','kelas','jurusan','orangtua'])));
    }

    public function update(UpdateSiswaRequest $request, ?Siswa $siswa): JsonResponse
    {
        if (!$siswa) {
            return response()->json([
                'success' => false,
                'message' => 'Data siswa tidak ditemukan',
                'errors'  => ['id' => ['Siswa dengan ID tersebut tidak ada']]
            ], 404);
        }

        $validated = $request->validated();
        $oldFoto = $siswa->foto;

        if ($request->hasFile('foto')) {
            if ($oldFoto) {
                Storage::disk('public')->delete($oldFoto);
            }
            $validated['foto'] = $request->file('foto')->store('siswa/foto', 'public');
        }

        foreach (['nis','user_id'] as $field) {
            if (!empty($validated[$field]) &&
                Siswa::where($field, $validated[$field])
                     ->where('id','<>',$siswa->id)
                     ->exists()) {
                if (!empty($validated['foto']) && $validated['foto'] !== $oldFoto) {
                    Storage::disk('public')->delete($validated['foto']);
                }
                return response()->json([
                    'success' => false,
                    'message' => strtoupper($field).' sudah terdaftar.',
                    'errors'  => [$field => [strtoupper($field).' sudah terdaftar.']]
                ], 409);
            }
        }

        try {
            DB::transaction(fn() => $siswa->update($validated));
            $siswa->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil diperbarui.',
                'data'    => new SiswaResource($siswa->load(['user','kelas','jurusan','orangtua']))
            ], 200);
        } catch (Throwable $e) {
            if (!empty($validated['foto'] ?? null) && $validated['foto'] !== $oldFoto) {
                Storage::disk('public')->delete($validated['foto']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui siswa',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], 500);
        }
    }

    public function destroy(?Siswa $siswa): JsonResponse
    {
        if (!$siswa) {
            return response()->json([
                'success' => false,
                'message' => 'Data siswa tidak ditemukan',
                'errors'  => ['id' => ['Siswa dengan ID tersebut tidak ada']]
            ], 404);
        }

        $oldFoto = $siswa->foto;

        try {
            DB::transaction(fn() => $siswa->delete());

            if ($oldFoto) {
                Storage::disk('public')->delete($oldFoto);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil dihapus'
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus siswa',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], 500);
        }
    }
}
