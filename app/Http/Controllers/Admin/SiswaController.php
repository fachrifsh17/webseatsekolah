<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Http\Requests\StoreSiswaRequest;
use App\Http\Requests\UpdateSiswaRequest;
use App\Http\Resources\SiswaResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class SiswaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin,Guru,Pembimbing')->only(['index', 'show']);
        $this->middleware('role:Admin')->except(['index', 'show']);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Siswa::with(['user', 'kelas.jurusan', 'orangtua']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', '%' . $search . '%')
                  ->orWhereHas('kelas', function ($queryKelas) use ($search) {
                      $queryKelas->where('nama_kelas', 'like', '%' . $search . '%');
                  });
            });
        }

        $perPage = $request->filled('search') ? 10 : 20;
        $data    = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => SiswaResource::collection($data),
            'meta'    => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'per_page'     => $data->perPage(),
                'total'        => $data->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StoreSiswaRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $fillable  = (new Siswa())->getFillable();
        $data      = Arr::only($validated, $fillable);

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('siswa/foto', 'public');
        }

        try {
            $siswa = DB::transaction(function () use ($data) {
                return Siswa::create($data);
            });

            $siswa->load(['user', 'kelas.jurusan', 'orangtua']);

            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil ditambahkan',
                'data'    => new SiswaResource($siswa)
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Failed to create siswa', ['payload' => $data, 'error' => $e->getMessage()]);
            if (!empty($data['foto'])) {
                Storage::disk('public')->delete($data['foto']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan siswa',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Siswa $siswa): JsonResponse
    {
        $siswa->load(['user', 'kelas.jurusan', 'orangtua']);
        return response()->json([
            'success' => true,
            'data'    => new SiswaResource($siswa)
        ], Response::HTTP_OK);
    }

    public function update(UpdateSiswaRequest $request, Siswa $siswa): JsonResponse
    {
        $validated = $request->validated();
        $fillable  = (new Siswa())->getFillable();
        $data      = Arr::only($validated, $fillable);
        $oldFoto   = $siswa->foto;

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('siswa/foto', 'public');
        }

        try {
            DB::transaction(function () use ($siswa, $data) {
                $siswa->update($data);
            });

            if ($oldFoto && isset($data['foto'])) {
                Storage::disk('public')->delete($oldFoto);
            }

            $siswa->refresh()->load(['user', 'kelas.jurusan', 'orangtua']);

            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil diperbarui',
                'data'    => new SiswaResource($siswa)
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to update siswa', [
                'siswa_id' => (string) $siswa->id,
                'payload'  => $data,
                'error'    => $e->getMessage()
            ]);
            if (isset($data['foto'])) {
                Storage::disk('public')->delete($data['foto']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui siswa',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Siswa $siswa): JsonResponse
    {
        try {
            DB::transaction(function () use ($siswa) {
                $siswa->delete();
            });

            if ($siswa->foto) {
                Storage::disk('public')->delete($siswa->foto);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil dihapus'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to delete siswa', ['siswa_id' => (string) $siswa->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus siswa',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
