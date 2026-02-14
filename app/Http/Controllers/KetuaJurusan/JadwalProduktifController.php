<?php

namespace App\Http\Controllers\KetuaJurusan;

use App\Http\Controllers\Controller;
use App\Models\JadwalProduktif;
use App\Models\TahunAjaran;
use App\Http\Requests\StoreJadwalProduktifRequest;
use App\Http\Requests\UpdateJadwalProduktifRequest;
use App\Http\Resources\JadwalProduktifResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class JadwalProduktifController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);
        
        $this->authorizeResource(JadwalProduktif::class, 'jadwal_produktif');
    }

    public function index(): JsonResponse
    {
        $perPage = min((int) request()->get('per_page', 20), 100);
        $user = Auth::user();

        $taAktif = TahunAjaran::where('is_active', 1)->first();
        if (!$taAktif) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada Tahun Ajaran yang aktif.'
            ], Response::HTTP_NOT_FOUND);
        }

        $jurusanId = $user->guruStaf?->jurusan_id;
        if (!$jurusanId) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda tidak terhubung dengan data jurusan.'
            ], Response::HTTP_FORBIDDEN);
        }

        $query = JadwalProduktif::with(['jurusan'])
                 ->where('tahun_ajaran_id', $taAktif->id)
                 ->where('jurusan_id', $jurusanId);

        $jadwal = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'info'    => [
                'tahun_ajaran' => $taAktif->nama,
                'semester'     => $taAktif->semester,
                'jurusan_anda' => $user->guruStaf?->jurusan?->nama_jurusan
            ],
            'data'    => JadwalProduktifResource::collection($jadwal),
            'meta'    => [
                'current_page' => $jadwal->currentPage(),
                'last_page'    => $jadwal->lastPage(),
                'per_page'     => $jadwal->perPage(),
                'total'        => $jadwal->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function show(JadwalProduktif $jadwalProduktif): JsonResponse
    {
        $user = Auth::user();
        $jurusanId = $user->guruStaf?->jurusan_id;

        if ($jadwalProduktif->jurusan_id !== $jurusanId) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Ini bukan jadwal jurusan Anda.'
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'success' => true,
            'data'    => new JadwalProduktifResource($jadwalProduktif->load(['jurusan']))
        ], Response::HTTP_OK);
    }

    public function store(StoreJadwalProduktifRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = Auth::user();
        
        $taAktif = TahunAjaran::where('is_active', 1)->first();
        if (!$taAktif) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal simpan, tidak ada Tahun Ajaran aktif.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $jurusanId = $user->guruStaf?->jurusan_id;
        if (!$jurusanId) {
            return response()->json([
                'success' => false,
                'message' => 'Data jurusan Anda tidak ditemukan.'
            ], Response::HTTP_FORBIDDEN);
        }

        $validated['jurusan_id'] = $jurusanId;
        $validated['tahun_ajaran_id'] = $taAktif->id;

        if (JadwalProduktif::where('jurusan_id', $jurusanId)->where('tahun_ajaran_id', $taAktif->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Jurusan Anda sudah memiliki jadwal di periode ini.'
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
                'data'    => new JadwalProduktifResource($jadwal->load(['jurusan']))
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            if (!empty($validated['file_jadwal_path'])) {
                Storage::disk('public')->delete($validated['file_jadwal_path']);
            }
            Log::error('Store Error Ketua Jurusan: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan jadwal.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateJadwalProduktifRequest $request, JadwalProduktif $jadwalProduktif): JsonResponse
    {
        $validated = $request->validated();
        $user = Auth::user();
        $jurusanId = $user->guruStaf?->jurusan_id;

        if ($jadwalProduktif->jurusan_id !== $jurusanId) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki otoritas mengubah jadwal jurusan lain.'
            ], Response::HTTP_FORBIDDEN);
        }

        $validated['jurusan_id'] = $jurusanId;

        if ($request->hasFile('file_jadwal_path')) {
            if (!empty($jadwalProduktif->file_jadwal_path)) {
                Storage::disk('public')->delete($jadwalProduktif->file_jadwal_path);
            }
            $validated['file_jadwal_path'] = $request->file('file_jadwal_path')->store('jadwal_produktif', 'public');
        }

        try {
            $jadwalProduktif->update($validated);
            return response()->json([
                'success' => true,
                'message' => 'Jadwal produktif berhasil diperbarui.',
                'data'    => new JadwalProduktifResource($jadwalProduktif->load(['jurusan']))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Update Error Ketua Jurusan: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(JadwalProduktif $jadwalProduktif): JsonResponse
    {
        $user = Auth::user();
        if ($jadwalProduktif->jurusan_id !== $user->guruStaf?->jurusan_id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak.'
            ], Response::HTTP_FORBIDDEN);
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
            Log::error('Delete Error Ketua Jurusan: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}