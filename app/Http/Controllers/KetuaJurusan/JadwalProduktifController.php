<?php

namespace App\Http\Controllers\KetuaJurusan;

use App\Http\Controllers\Controller;
use App\Models\JadwalProduktif;
use App\Http\Requests\StoreJadwalProduktifRequest;
use App\Http\Requests\UpdateJadwalProduktifRequest;
use App\Http\Resources\JadwalProduktifResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class JadwalProduktifController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin,Guru');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $perPage = min((int) request()->get('per_page', 20), 100);
        $user = Auth::user();

        $query = JadwalProduktif::with(['jurusan', 'guruStaf']);

        if ($user && !$user->roles()->where('role_name', 'admin')->exists()) {
            $jurusanId = $user->guruStaf?->jurusan_id;
            $query->where('jurusan_id', $jurusanId);
        }

        $jadwal = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => JadwalProduktifResource::collection($jadwal),
            'meta'    => [
                'current_page' => $jadwal->currentPage(),
                'last_page'    => $jadwal->lastPage(),
                'per_page'     => $jadwal->perPage(),
                'total'        => $jadwal->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function show($id): JsonResponse
    {
        $jadwalProduktif = JadwalProduktif::with(['jurusan', 'guruStaf'])->find($id);

        if (!$jadwalProduktif) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan.'
            ], Response::HTTP_NOT_FOUND);
        }

        $user = Auth::user();

        if ($user && !$user->roles()->where('role_name', 'admin')->exists()) {
            if ($jadwalProduktif->jurusan_id !== $user->guruStaf?->jurusan_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses ke jadwal jurusan lain.'
                ], Response::HTTP_FORBIDDEN);
            }
        }

        return response()->json([
            'success' => true,
            'data'    => new JadwalProduktifResource($jadwalProduktif)
        ], Response::HTTP_OK);
    }

    public function store(StoreJadwalProduktifRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false, 
                'message' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $isAdmin = $user->roles()->where('role_name', 'admin')->exists();

        if (!$isAdmin) {
            $guru = $user->guruStaf;
            if (!$guru || !$guru->jurusan_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun belum terhubung dengan data guru atau jurusan.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $validated['guru_staf_id'] = $guru->id;
            $validated['jurusan_id'] = $guru->jurusan_id;
        }

        if (JadwalProduktif::where('jurusan_id', $validated['jurusan_id'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Jurusan ini sudah memiliki jadwal produktif'
            ], Response::HTTP_CONFLICT);
        }

        if ($request->hasFile('file_jadwal_path')) {
            $validated['file_jadwal_path'] = $request->file('file_jadwal_path')->store('jadwal_produktif', 'public');
        }

        try {
            $jadwal = JadwalProduktif::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Jadwal produktif berhasil ditambahkan.',
                'data'    => new JadwalProduktifResource($jadwal->load(['jurusan', 'guruStaf']))
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            if (!empty($validated['file_jadwal_path'])) {
                Storage::disk('public')->delete($validated['file_jadwal_path']);
            }
            Log::error('Store Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan jadwal produktif',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateJadwalProduktifRequest $request, $id): JsonResponse
    {
        $jadwalProduktif = JadwalProduktif::find($id);
        if (!$jadwalProduktif) {
            return response()->json([
                'success' => false, 
                'message' => 'Data tidak ditemukan.'
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();
        $user = Auth::user();
        $isAdmin = $user && $user->roles()->where('role_name', 'admin')->exists();

        if (!$isAdmin) {
            if ($jadwalProduktif->jurusan_id !== $user->guruStaf?->jurusan_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki otoritas untuk mengubah jadwal jurusan lain.'
                ], Response::HTTP_FORBIDDEN);
            }
            $validated['guru_staf_id'] = $user->guruStaf?->id;
            $validated['jurusan_id'] = $user->guruStaf?->jurusan_id;
        }

        if ($request->hasFile('file_jadwal_path')) {
            $newFilePath = $request->file('file_jadwal_path')->store('jadwal_produktif', 'public');
            if (!empty($jadwalProduktif->file_jadwal_path)) {
                Storage::disk('public')->delete($jadwalProduktif->file_jadwal_path);
            }
            $validated['file_jadwal_path'] = $newFilePath;
        }

        try {
            $jadwalProduktif->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Jadwal produktif berhasil diperbarui.',
                'data'    => new JadwalProduktifResource($jadwalProduktif->load(['jurusan', 'guruStaf']))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Update Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy($id): JsonResponse
    {
        $jadwalProduktif = JadwalProduktif::find($id);
        if (!$jadwalProduktif) {
            return response()->json([
                'success' => false, 
                'message' => 'Data tidak ditemukan.'
            ], Response::HTTP_NOT_FOUND);
        }

        $user = Auth::user();

        if ($user && !$user->roles()->where('role_name', 'admin')->exists()) {
            if ($jadwalProduktif->jurusan_id !== $user->guruStaf?->jurusan_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak.'
                ], Response::HTTP_FORBIDDEN);
            }
        }

        try {
            if (!empty($jadwalProduktif->file_jadwal_path)) {
                Storage::disk('public')->delete($jadwalProduktif->file_jadwal_path);
            }

            $jadwalProduktif->delete();

            return response()->json([
                'success' => true,
                'message' => 'Jadwal produktif berhasil dihapus',
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Delete Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}