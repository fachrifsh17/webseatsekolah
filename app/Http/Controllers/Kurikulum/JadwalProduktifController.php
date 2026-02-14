<?php

namespace App\Http\Controllers\Kurikulum;

use App\Http\Controllers\Controller;
use App\Models\JadwalProduktif;
use App\Models\TahunAjaran;
use App\Http\Requests\{StoreJadwalProduktifRequest, UpdateJadwalProduktifRequest};
use App\Http\Resources\JadwalProduktifResource;
use Illuminate\Support\Facades\{Storage, Log};
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

    /**
     * Menampilkan semua jadwal produktif pada Tahun Ajaran Aktif
     */
    public function index(): JsonResponse
    {
        $perPage = min((int) request()->get('per_page', 20), 100);

        // Cari Tahun Ajaran Aktif
        $taAktif = TahunAjaran::where('is_active', 1)->first();

        if (!$taAktif) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada Tahun Ajaran yang aktif.'
            ], Response::HTTP_NOT_FOUND);
        }

        $jadwal = JadwalProduktif::with(['jurusan'])
            ->where('tahun_ajaran_id', $taAktif->id)
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'info'    => [
                'tahun_ajaran' => $taAktif->nama,
                'semester'     => $taAktif->semester
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
        return response()->json([
            'success' => true,
            'data'    => new JadwalProduktifResource($jadwalProduktif->load(['jurusan']))
        ], Response::HTTP_OK);
    }

    public function store(StoreJadwalProduktifRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // 1. Ambil Tahun Ajaran Aktif
        $taAktif = TahunAjaran::where('is_active', 1)->first();
        if (!$taAktif) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal simpan, tidak ada Tahun Ajaran yang aktif.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // 2. Isi otomatis tahun_ajaran_id
        $validated['tahun_ajaran_id'] = $taAktif->id;

        // 3. Cek Conflict: 1 Jurusan hanya boleh 1 Jadwal di TA Aktif
        $exists = JadwalProduktif::where('jurusan_id', $validated['jurusan_id'])
            ->where('tahun_ajaran_id', $taAktif->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => "Jurusan ini sudah memiliki jadwal pada periode {$taAktif->nama}."
            ], Response::HTTP_CONFLICT);
        }

        if ($request->hasFile('file_jadwal_path')) {
            $validated['file_jadwal_path'] = $request->file('file_jadwal_path')->store('jadwal_produktif', 'public');
        }

        try {
            $jadwal = JadwalProduktif::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Jadwal produktif berhasil ditambahkan oleh tim Kurikulum.',
                'data'    => new JadwalProduktifResource($jadwal->load(['jurusan']))
            ], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            if (isset($validated['file_jadwal_path'])) {
                Storage::disk('public')->delete($validated['file_jadwal_path']);
            }
            Log::error('Kurikulum Store Jadwal Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan jadwal produktif.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateJadwalProduktifRequest $request, JadwalProduktif $jadwalProduktif): JsonResponse
    {
        $validated = $request->validated();

        // Jika jurusan diubah, validasi conflict di tahun ajaran yang melekat pada record tersebut
        if (isset($validated['jurusan_id']) && $validated['jurusan_id'] != $jadwalProduktif->jurusan_id) {
            $exists = JadwalProduktif::where('jurusan_id', $validated['jurusan_id'])
                ->where('tahun_ajaran_id', $jadwalProduktif->tahun_ajaran_id)
                ->where('id', '!=', $jadwalProduktif->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jurusan tujuan sudah memiliki jadwal di periode tersebut.'
                ], Response::HTTP_CONFLICT);
            }
        }

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
                'message' => 'Jadwal produktif berhasil diperbarui oleh tim Kurikulum.',
                'data'    => new JadwalProduktifResource($jadwalProduktif->load(['jurusan']))
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Kurikulum Update Jadwal Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui jadwal produktif.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(JadwalProduktif $jadwalProduktif): JsonResponse
    {
        try {
            if (!empty($jadwalProduktif->file_jadwal_path)) {
                Storage::disk('public')->delete($jadwalProduktif->file_jadwal_path);
            }

            $jadwalProduktif->delete();

            return response()->json([
                'success' => true,
                'message' => 'Jadwal produktif berhasil dihapus oleh tim Kurikulum.'
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Kurikulum Delete Jadwal Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus jadwal produktif.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}