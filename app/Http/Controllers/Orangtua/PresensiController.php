<?php

namespace App\Http\Controllers\Orangtua;

use App\Http\Controllers\Controller;
use App\Models\{Presensi, Siswa, TahunAjaran};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            $tahunAktif = TahunAjaran::where('is_active', true)->first();
            $tahunAjaranId = $request->get('tahun_ajaran_id', $tahunAktif?->id);

            $siswaIds = $this->getSiswaIds($user, $tahunAjaranId);

            if (empty($siswaIds)) {
                return $this->emptyResponse($tahunAktif);
            }

            $query = $this->buildQuery($siswaIds, $tahunAjaranId, $request);
            
            if ($query instanceof JsonResponse) return $query;

            $summary = $this->getSummary($query);
            $tahunTampil = $tahunAjaranId ? TahunAjaran::find($tahunAjaranId) : $tahunAktif;
            $perPage = $request->integer('per_page', 10);
            
            $data = $query->with(['siswa', 'kelas'])->orderBy('tanggal', 'desc')->paginate($perPage);

            return $this->formatResponse($data, $summary, $tahunTampil);
        } catch (Throwable $e) {
            Log::error('Presensi Orangtua Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data presensi'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function getSiswaIds($user, $tahunAjaranId)
    {
        return Siswa::whereHas('orangtua', fn($q) => $q->where('user_id', $user->id))
            ->whereHas('riwayatKelas', function($q) use ($tahunAjaranId) {
                $q->where('tahun_ajaran_id', $tahunAjaranId);
            })
            ->pluck('id')
            ->toArray();
    }

    private function buildQuery(array $siswaIds, $tahunAjaranId, Request $request)
    {
        $query = Presensi::whereIn('siswa_id', $siswaIds);

        if ($tahunAjaranId) {
            $query->where('tahun_ajaran_id', $tahunAjaranId);
        }

        if ($request->filled('siswa_id')) {
            if (!in_array($request->siswa_id, $siswaIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak atau siswa tidak terdaftar di periode ini'
                ], Response::HTTP_FORBIDDEN);
            }
            $query->where('siswa_id', $request->siswa_id);
        }

        return $query;
    }

    private function getSummary($query)
    {
        return (clone $query)->select(
            DB::raw("CAST(SUM(CASE WHEN status = 'Hadir' THEN 1 ELSE 0 END) AS SIGNED) as hadir"),
            DB::raw("CAST(SUM(CASE WHEN status = 'Izin' THEN 1 ELSE 0 END) AS SIGNED) as izin"),
            DB::raw("CAST(SUM(CASE WHEN status = 'Sakit' THEN 1 ELSE 0 END) AS SIGNED) as sakit"),
            DB::raw("CAST(SUM(CASE WHEN status = 'Alpa' THEN 1 ELSE 0 END) AS SIGNED) as alpa"),
            DB::raw("COUNT(*) as total_hari")
        )->first();
    }

    private function formatResponse($data, $summary, $tahunTampil)
    {
        $paginationData = $data->toArray();

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
                'kelas' => $item->kelas?->nama_kelas ?? '-',
                'tanggal' => Carbon::parse($item->tanggal)->format('d-m-Y'),
                'status' => $item->status,
                'keterangan' => $item->keterangan,
            ]),
            'meta' => [
                'current_page' => $paginationData['current_page'],
                'last_page'    => $paginationData['last_page'],
                'total'        => $paginationData['total'],
            ],
        ], Response::HTTP_OK);
    }

    public function listAnak(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tahunAktif = TahunAjaran::where('is_active', true)->first();
            $tahunAjaranId = $request->get('tahun_ajaran_id', $tahunAktif?->id);

            $anak = Siswa::whereHas('orangtua', fn($q) => $q->where('user_id', $user->id))
                ->whereHas('riwayatKelas', function($q) use ($tahunAjaranId) {
                    $q->where('tahun_ajaran_id', $tahunAjaranId);
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

    private function emptyResponse($tahunAktif)
    {
        return response()->json([
            'success' => true,
            'message' => 'Data presensi tidak ditemukan.',
            'info' => [
                'tahun_ajaran' => $tahunAktif?->nama,
                'semester' => $tahunAktif?->semester,
            ],
            'summary' => ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0, 'total_hari' => 0],
            'data' => [],
            'meta' => ['total' => 0]
        ], Response::HTTP_OK);
    }
}