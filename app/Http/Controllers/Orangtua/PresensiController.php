<?php

namespace App\Http\Controllers\Orangtua;

use App\Http\Controllers\Controller;
use App\Models\{Presensi, Siswa, Semester};
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
            $semesterAktif = Semester::where('is_active', true)->first();
            $semesterId = $request->query('semester_id', $semesterAktif?->id);

            // 1. Ambil ID anak-anak yang aktif saja
            $siswaIds = $this->getSiswaIds($user, $semesterId);

            if (empty($siswaIds)) {
                return $this->emptyResponse($semesterAktif);
            }

            // 2. Bangun query utama
            $query = $this->buildQuery($siswaIds, $semesterId, $request);
            
            // Cek jika buildQuery mengembalikan error response (misal akses ditolak)
            if ($query instanceof JsonResponse) return $query;

            // 3. Ambil ringkasan statistik (hadir, izin, sakit, alpa)
            $summary = $this->getSummary($query);
            
            $semesterTampil = $semesterId ? Semester::with('tahunAjaran')->find($semesterId) : $semesterAktif;
            $perPage = $request->integer('per_page', 10);
            
            // 4. Eksekusi pagination
            $data = $query->orderBy('presensi.tanggal', 'desc')->paginate($perPage);

            return $this->formatResponse($data, $summary, $semesterTampil);
        } catch (Throwable $e) {
            Log::error('Presensi Orangtua Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data presensi'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function getSiswaIds($user, $semesterId)
    {
        return Siswa::where('is_active', true) // Filter Siswa Aktif
            ->whereHas('orangtua', fn($q) => $q->where('user_id', $user->id))
            ->whereHas('riwayatKelas', function($q) use ($semesterId) {
                $q->where('semester_id', $semesterId);
            })
            ->pluck('id')
            ->toArray();
    }

    private function buildQuery(array $siswaIds, $semesterId, Request $request)
    {
        $query = DB::table('presensi_detail')
            ->join('presensi', 'presensi_detail.presensi_id', '=', 'presensi.id')
            ->join('siswa', 'presensi_detail.siswa_id', '=', 'siswa.id')
            ->leftJoin('kelas', 'presensi.kelas_id', '=', 'kelas.id')
            ->whereIn('presensi_detail.siswa_id', $siswaIds)
            ->where('siswa.is_active', true) // Double check filter aktif di join
            ->select(
                'presensi_detail.id',
                'presensi_detail.status',
                'presensi_detail.keterangan',
                'presensi.tanggal',
                'siswa.nama_lengkap as nama_siswa',
                'kelas.nama_kelas'
            );

        if ($semesterId) {
            $query->where('presensi.semester_id', $semesterId);
        }

        // Filter per anak tertentu jika dipilih di dropdown
        if ($request->filled('siswa_id')) {
            if (!in_array($request->siswa_id, $siswaIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak atau siswa tidak aktif di periode ini'
                ], Response::HTTP_FORBIDDEN);
            }
            $query->where('presensi_detail.siswa_id', $request->siswa_id);
        }

        return $query;
    }

    private function getSummary($query)
    {
        // Mengkloning query agar filter (semester/siswa_id) tetap terbawa ke summary
        return (clone $query)->select(
            DB::raw("CAST(SUM(CASE WHEN presensi_detail.status = 'Hadir' THEN 1 ELSE 0 END) AS SIGNED) as hadir"),
            DB::raw("CAST(SUM(CASE WHEN presensi_detail.status = 'Izin' THEN 1 ELSE 0 END) AS SIGNED) as izin"),
            DB::raw("CAST(SUM(CASE WHEN presensi_detail.status = 'Sakit' THEN 1 ELSE 0 END) AS SIGNED) as sakit"),
            DB::raw("CAST(SUM(CASE WHEN presensi_detail.status = 'Alpa' THEN 1 ELSE 0 END) AS SIGNED) as alpa"),
            DB::raw("COUNT(*) as total_hari")
        )->first();
    }

    private function formatResponse($data, $summary, $semesterTampil)
    {
        $paginationData = $data->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Data presensi berhasil diambil.',
            'info' => [
                'tahun_ajaran' => $semesterTampil?->tahunAjaran?->nama,
                'semester'     => $semesterTampil?->nama,
            ],
            'summary' => [
                'hadir'      => (int)($summary->hadir ?? 0),
                'izin'       => (int)($summary->izin ?? 0),
                'sakit'      => (int)($summary->sakit ?? 0),
                'alpa'       => (int)($summary->alpa ?? 0),
                'total_hari' => (int)($summary->total_hari ?? 0)
            ],
            'data' => collect($data->items())->map(fn($item) => [
                'id'         => $item->id,
                'nama_siswa' => $item->nama_siswa,
                'kelas'      => $item->nama_kelas ?? '-',
                'tanggal'    => Carbon::parse($item->tanggal)->format('d-m-Y'),
                'status'     => $item->status,
                'keterangan' => $item->keterangan,
            ]),
            'meta' => [
                'current_page' => $paginationData['current_page'],
                'last_page'    => $paginationData['last_page'],
                'total'        => $paginationData['total'],
                'per_page'     => $paginationData['per_page'],
            ],
            'links' => [
                'first' => $paginationData['first_page_url'],
                'last'  => $paginationData['last_page_url'],
                'prev'  => $paginationData['prev_page_url'],
                'next'  => $paginationData['next_page_url'],
            ],
        ], Response::HTTP_OK);
    }

    public function listAnak(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $semesterAktif = Semester::where('is_active', true)->first();
            $semesterId = $request->query('semester_id', $semesterAktif?->id);

            // Pastikan hanya anak yang aktif yang muncul di daftar dropdown
            $anak = Siswa::where('is_active', true) 
                ->whereHas('orangtua', fn($q) => $q->where('user_id', $user->id))
                ->whereHas('riwayatKelas', function($q) use ($semesterId) {
                    $q->where('semester_id', $semesterId);
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

    private function emptyResponse($semesterAktif)
    {
        return response()->json([
            'success' => true,
            'message' => 'Data presensi tidak ditemukan.',
            'info' => [
                'tahun_ajaran' => $semesterAktif?->tahunAjaran?->nama,
                'semester'     => $semesterAktif?->nama,
            ],
            'summary' => ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0, 'total_hari' => 0],
            'data'    => [],
            'meta'    => [
                'current_page' => 1,
                'last_page'    => 1,
                'total'        => 0,
                'per_page'     => 10,
            ],
            'links' => [
                'first' => null,
                'last'  => null,
                'prev'  => null,
                'next'  => null,
            ],
        ], Response::HTTP_OK);
    }
}