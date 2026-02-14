<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JadwalProduktif;
use App\Models\TahunAjaran;
use App\Http\Requests\StoreJadwalProduktifRequest;
use App\Http\Requests\UpdateJadwalProduktifRequest;
use App\Http\Resources\JadwalProduktifResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class JadwalProduktifController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);
        
        // Pastikan authorizeResource menggunakan model yang tepat
        $this->authorizeResource(JadwalProduktif::class, 'jadwal_produktif');
    }

    /**
     * Menampilkan jadwal berdasarkan Tahun Ajaran yang sedang Aktif (is_active = 1)
     */
    public function index(): JsonResponse
    {
        $perPage = min((int) request()->get('per_page', 20), 100);

        // Cari Tahun Ajaran yang sedang Aktif
        $taAktif = TahunAjaran::where('is_active', 1)->first();

        if (!$taAktif) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada Tahun Ajaran yang aktif saat ini.'
            ], Response::HTTP_NOT_FOUND);
        }

        $jadwal = JadwalProduktif::with(['jurusan'])
            ->where('tahun_ajaran_id', $taAktif->id)
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'info'    => [
                'tahun_ajaran' => $taAktif->nama, // Menggunakan kolom 'nama' dari image_a35b17.png
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

    /**
     * Simpan jadwal baru dengan otomatis mengisi tahun_ajaran_id dari TA Aktif
     */
    public function store(StoreJadwalProduktifRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        // Ambil Tahun Ajaran Aktif (Contoh: TA004 berdasarkan gambar Anda)
        $taAktif = TahunAjaran::where('is_active', 1)->first();
        
        if (!$taAktif) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan. Sistem tidak menemukan Tahun Ajaran yang aktif.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Isi otomatis tahun_ajaran_id
        $validated['tahun_ajaran_id'] = $taAktif->id;

        // Validasi: 1 Jurusan hanya boleh punya 1 Jadwal di Tahun Ajaran yang Aktif
        $conflict = JadwalProduktif::where('jurusan_id', $validated['jurusan_id'])
            ->where('tahun_ajaran_id', $taAktif->id)
            ->exists();

        if ($conflict) {
            return response()->json([
                'success' => false,
                'message' => "Jurusan ini sudah memiliki jadwal untuk periode {$taAktif->nama} {$taAktif->semester}."
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
            if (isset($validated['file_jadwal_path'])) {
                Storage::disk('public')->delete($validated['file_jadwal_path']);
            }
            Log::error('Error Store Jadwal:', ['msg' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat menyimpan data.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateJadwalProduktifRequest $request, JadwalProduktif $jadwalProduktif): JsonResponse
    {
        $validated = $request->validated();
        
        // Jika jurusan diubah, pastikan jurusan baru belum punya jadwal di TA yang sama
        if (isset($validated['jurusan_id']) && $validated['jurusan_id'] != $jadwalProduktif->jurusan_id) {
            $conflict = JadwalProduktif::where('jurusan_id', $validated['jurusan_id'])
                ->where('tahun_ajaran_id', $jadwalProduktif->tahun_ajaran_id)
                ->where('id', '!=', $jadwalProduktif->id)
                ->exists();

            if ($conflict) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jurusan tujuan sudah memiliki jadwal di periode ini.'
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
                'message' => 'Jadwal produktif berhasil diperbarui.',
                'data'    => new JadwalProduktifResource($jadwalProduktif->load(['jurusan']))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Error Update Jadwal:', ['msg' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data.'
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
                'message' => 'Jadwal produktif berhasil dihapus.'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}