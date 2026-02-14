<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\JadwalProduktif;
use App\Models\TahunAjaran;
use App\Http\Resources\JadwalProduktifResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class JadwalProduktifController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Siswa');
    }

    /**
     * Menampilkan jadwal produktif khusus untuk jurusan siswa di TA aktif
     */
    public function index(): JsonResponse
    {
        try {
            // Ambil data siswa dan kelas untuk mendapatkan jurusan_id
            $siswa = Auth::user()->siswa()->with('kelas')->first();

            if (!$siswa || !$siswa->kelas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data kelas atau jurusan siswa tidak ditemukan.'
                ], Response::HTTP_NOT_FOUND);
            }

            // Cari Tahun Ajaran Aktif
            $taAktif = TahunAjaran::where('is_active', 1)->first();

            if (!$taAktif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada Tahun Ajaran yang aktif.'
                ], Response::HTTP_NOT_FOUND);
            }

            $perPage = min((int) request()->get('per_page', 20), 100);

            // Filter: Hanya jurusan siswa DAN Tahun Ajaran Aktif
            $jadwal = JadwalProduktif::with(['jurusan'])
                ->where('jurusan_id', $siswa->kelas->jurusan_id)
                ->where('tahun_ajaran_id', $taAktif->id)
                ->latest()
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Daftar jadwal produktif berhasil diambil.',
                'info'    => [
                    'tahun_ajaran' => $taAktif->nama,
                    'semester'     => $taAktif->semester,
                    'jurusan'      => $siswa->kelas->jurusan?->nama_jurusan
                ],
                'data'    => JadwalProduktifResource::collection($jadwal),
                'meta'    => [
                    'current_page' => $jadwal->currentPage(),
                    'last_page'    => $jadwal->lastPage(),
                    'per_page'     => (int) $jadwal->perPage(),
                    'total'        => $jadwal->total(),
                ],
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data jadwal produktif.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Menampilkan detail jadwal (dengan proteksi jurusan)
     */
    public function show($id): JsonResponse
    {
        try {
            $siswa = Auth::user()->siswa()->with('kelas')->first();
            
            // Ambil Tahun Ajaran Aktif
            $taAktif = TahunAjaran::where('is_active', 1)->first();

            $jadwal = JadwalProduktif::with(['jurusan'])
                ->where('tahun_ajaran_id', $taAktif?->id)
                ->findOrFail($id);

            // Proteksi: Pastikan siswa tidak mengakses jadwal jurusan lain via ID manual
            if ($jadwal->jurusan_id !== $siswa->kelas->jurusan_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses dilarang. Anda hanya dapat melihat jadwal jurusan sendiri.'
                ], Response::HTTP_FORBIDDEN);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail jadwal produktif berhasil diambil.',
                'data'    => new JadwalProduktifResource($jadwal)
            ], Response::HTTP_OK);
            
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal produktif tidak ditemukan atau tidak aktif.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_NOT_FOUND);
        }
    }
}