<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\{Presensi, Semester};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Auth, DB, Log};
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;
use Throwable;

class PresensiController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Siswa');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $siswa = $user->siswa;
            $siswaId = $user->siswa_id ?? $siswa?->id;

            if (!$siswaId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data profil siswa tidak ditemukan.'
                ], Response::HTTP_NOT_FOUND);
            }

            $semesterAktif = Semester::where('is_active', 1)->first();
            $semesterId = $request->query('semester_id', $semesterAktif?->id);

            $query = $this->buildQuery($siswaId, $semesterId, $request);
            $summary = $this->getSummary($query);
            
            $riwayat = $siswa->riwayatKelas()
                ->where('siswa_kelas.semester_id', $semesterId)
                ->with('kelas')
                ->first();

            $semesterTampil = $semesterId ? Semester::find($semesterId) : $semesterAktif;
            $perPage = $request->integer('per_page', 10);
            $paginator = $query->orderBy('tanggal', 'desc')->orderBy('id', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Data presensi berhasil diambil.',
                'header' => [
                    'nama' => $siswa?->nama_lengkap,
                    'kelas' => $riwayat?->kelas?->nama_kelas ?? 'Tanpa Kelas',
                    'tahun_ajaran' => $semesterTampil?->tahunAjaran?->nama ?? 'Tidak Diketahui',
                    'semester' => $semesterTampil?->nama ?? '-',
                ],
                'summary' => [
                    'hadir' => (int)($summary->hadir ?? 0),
                    'izin'  => (int)($summary->izin ?? 0),
                    'sakit' => (int)($summary->sakit ?? 0),
                    'alpa'  => (int)($summary->alpa ?? 0),
                    'total_hari' => (int)($summary->total_hari ?? 0)
                ],
                'data' => $this->mapData($paginator),
                'meta' => $this->formatPagination($paginator),
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Gagal mengambil daftar presensi: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data presensi.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function buildQuery($siswaId, $semesterId, Request $request)
    {
        $query = DB::table('presensi_detail')
            ->join('presensi', 'presensi_detail.presensi_id', '=', 'presensi.id')
            ->where('presensi_detail.siswa_id', $siswaId)
            ->select('presensi_detail.*', 'presensi.tanggal', 'presensi.semester_id', 'presensi.kelas_id');

        if ($semesterId) {
            $query->where('presensi.semester_id', $semesterId);
        }

        if ($request->filled('kelas_id')) {
            $query->where('presensi.kelas_id', $request->kelas_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('presensi_detail.status', 'like', "%{$search}%")
                  ->orWhere('presensi_detail.keterangan', 'like', "%{$search}%")
                  ->orWhere('presensi.tanggal', 'like', "%{$search}%");
            });
        }

        if ($request->filled('tanggal')) {
            $query->whereDate('presensi.tanggal', $request->tanggal);
        }

        return $query;
    }

    private function getSummary($query)
    {
        return (clone $query)->select(
            DB::raw("CAST(SUM(CASE WHEN presensi_detail.status = 'Hadir' THEN 1 ELSE 0 END) AS SIGNED) as hadir"),
            DB::raw("CAST(SUM(CASE WHEN presensi_detail.status = 'Izin' THEN 1 ELSE 0 END) AS SIGNED) as izin"),
            DB::raw("CAST(SUM(CASE WHEN presensi_detail.status = 'Sakit' THEN 1 ELSE 0 END) AS SIGNED) as sakit"),
            DB::raw("CAST(SUM(CASE WHEN presensi_detail.status = 'Alpa' THEN 1 ELSE 0 END) AS SIGNED) as alpa"),
            DB::raw("COUNT(*) as total_hari")
        )->first();
    }

    private function mapData($paginator)
    {
        return collect($paginator->items())->map(fn($item) => [
            'id' => $item->id,
            'tanggal' => Carbon::parse($item->tanggal)->format('Y-m-d'),
            'status' => $item->status,
            'keterangan' => $item->keterangan,
        ]);
    }

    private function formatPagination($paginator)
    {
        $data = $paginator->toArray();
        return [
            'current_page' => $data['current_page'],
            'last_page'    => $data['last_page'],
            'per_page'     => $data['per_page'],
            'total'        => $data['total'],
            'links'        => array_map(fn($link) => [
                'url'    => $link['url'],
                'label'  => $link['label'],
                'page'   => is_numeric($link['label']) ? (int) $link['label'] : null,
                'active' => $link['active'],
            ], $data['links']),
        ];
    }
}