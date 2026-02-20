<?php

namespace App\Http\Controllers\Orangtua;

use App\Http\Controllers\Controller;
use App\Models\{Presensi, Siswa, TahunAjaran};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Auth, DB, Log};
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;
use Throwable;

class PresensiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Orangtua');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // 1. Tentukan Tahun Ajaran Aktif
            $tahunAktif = TahunAjaran::where('is_active', true)->first();
            $tahunAjaranId = $request->get('tahun_ajaran_id', $tahunAktif?->id);

            // 2. Filter: Hanya ambil anak yang terhubung & memiliki KELAS AKTIF di tahun ajaran tersebut
            $children = Siswa::whereHas('orangtua', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->where('is_active', true)
            ->whereHas('kelas', function($q) use ($tahunAjaranId) {
                $q->where('is_active', true)
                  ->where('tahun_ajaran_id', $tahunAjaranId);
            })
            ->get(['id']);

            $siswaIds = $children->pluck('id')->toArray();

            if (empty($siswaIds)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Data presensi tidak ditemukan untuk periode ini.',
                    'info' => [
                        'tahun_ajaran' => $tahunAktif?->nama,
                        'semester' => $tahunAktif?->semester,
                    ],
                    'summary' => null,
                    'data' => []
                ], Response::HTTP_OK);
            }

            $query = Presensi::whereIn('siswa_id', $siswaIds);

            if ($tahunAjaranId) {
                $query->where('tahun_ajaran_id', $tahunAjaranId);
            }

            if ($request->filled('siswa_id')) {
                if (!in_array($request->siswa_id, $siswaIds)) {
                    return response()->json([
                        'success' => false, 
                        'message' => 'Akses ditolak atau siswa tidak aktif di kelas ini'
                    ], Response::HTTP_FORBIDDEN);
                }
                $query->where('siswa_id', $request->siswa_id);
            }

            // 3. Hitung Summary
            $summary = (clone $query)->select(
                DB::raw("SUM(CASE WHEN status = 'Hadir' THEN 1 ELSE 0 END) as hadir"),
                DB::raw("SUM(CASE WHEN status = 'Izin' THEN 1 ELSE 0 END) as izin"),
                DB::raw("SUM(CASE WHEN status = 'Sakit' THEN 1 ELSE 0 END) as sakit"),
                DB::raw("SUM(CASE WHEN status = 'Alpa' THEN 1 ELSE 0 END) as alpa"),
                DB::raw("COUNT(*) as total_hari")
            )->first();

            $perPage = $request->integer('per_page', 10);
            $data = $query->with(['siswa.kelas'])
                          ->orderBy('tanggal', 'desc')
                          ->paginate($perPage);

            $tahunTampil = $tahunAjaranId ? TahunAjaran::find($tahunAjaranId) : $tahunAktif;

            return response()->json([
                'success' => true,
                'message' => 'Data presensi berhasil diambil.',
                'info' => [
                    'tahun_ajaran' => $tahunTampil?->nama,
                    'semester' => $tahunTampil?->semester,
                ],
                'summary' => [
                    'hadir' => (int)($summary->hadir ?? 0),
                    'izin'  => (int)($summary->izin ?? 0),
                    'sakit' => (int)($summary->sakit ?? 0),
                    'alpa'  => (int)($summary->alpa ?? 0),
                    'total_hari' => (int)($summary->total_hari ?? 0)
                ],
                'data' => collect($data->items())->map(fn($item) => [
                    'id' => $item->id,
                    'nama_siswa' => $item->siswa?->nama_lengkap,
                    'kelas' => $item->siswa?->kelas?->nama_kelas,
                    'tanggal' => Carbon::parse($item->tanggal)->format('d-m-Y'),
                    'status' => $item->status,
                    'keterangan' => $item->keterangan,
                ]),
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                    'total' => $data->total(),
                ]
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Presensi Orangtua Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal memuat data presensi'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function listAnak(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tahunAktif = TahunAjaran::where('is_active', true)->first();
            $tahunAjaranId = $request->get('tahun_ajaran_id', $tahunAktif?->id);

            $anak = Siswa::whereHas('orangtua', function($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->where('is_active', true)
                ->whereHas('kelas', function($q) use ($tahunAjaranId) {
                    $q->where('is_active', true)
                      ->where('tahun_ajaran_id', $tahunAjaranId);
                })
                ->get(['id', 'nama_lengkap as nama']);

            return response()->json([
                'success' => true,
                'data' => $anak
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Gagal mengambil daftar anak'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}