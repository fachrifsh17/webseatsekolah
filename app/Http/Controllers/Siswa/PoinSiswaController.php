<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\{PoinSiswa, Semester};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Log};
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;
use Throwable;

class PoinSiswaController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', PoinSiswa::class);

            // 1. Ambil data siswa dan pastikan dia aktif
            $siswa = Auth::user()->siswa;
            if ($error = $this->validateSiswa($siswa)) return $error;

            // 2. Tentukan Semester ID
            $semesterAktif = Semester::where('is_active', true)->first();
            $semesterId = $request->get('semester_id', $semesterAktif?->id);

            // 3. Ambil data pendukung (Header & Summary)
            $semesterTampil = $semesterId ? Semester::with('tahunAjaran')->find($semesterId) : $semesterAktif;
            
            // Jika semester tidak ditemukan sama sekali
            if (!$semesterTampil && !$semesterId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data semester aktif tidak ditemukan.',
                ], Response::HTTP_NOT_FOUND);
            }

            $summaryData = $this->getSummaryAkumulatif($siswa->id);
            $query = $this->buildQuery($siswa->id, $semesterId, $request);
            $paginator = $this->getPaginator($query, $semesterId, $request);
            $kelasPeriode = $this->getKelasPeriode($siswa, $semesterId);

            return response()->json([
                'success' => true,
                'message' => 'Data poin berhasil diambil.',
                'header'  => [
                    'nama'         => $siswa->nama_lengkap,
                    'nis'          => $siswa->nis,
                    'kelas'        => $kelasPeriode?->kelas?->nama_kelas ?? '-',
                    'tahun_ajaran' => $semesterTampil?->tahunAjaran?->nama ?? '-',
                    'semester'     => $semesterTampil?->nama ?? '-',
                ],
                'summary' => [
                    'total_positif' => (int)($summaryData->total_plus ?? 0),
                    'total_negatif' => (int)($summaryData->total_minus ?? 0),
                    'total_catatan' => (int)($summaryData->total_catatan ?? 0),
                    'saldo_poin'    => (int)($summaryData->total_plus ?? 0) - (int)($summaryData->total_minus ?? 0),
                ],
                'data' => $this->mapData($paginator),
                'meta' => $this->formatPagination($paginator),
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Gagal mengambil poin siswa: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data poin.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function getSummaryAkumulatif($siswaId)
    {
        return PoinSiswa::where('siswa_id', $siswaId)
            ->select(
                DB::raw('CAST(SUM(poin_positif) AS SIGNED) as total_plus'),
                DB::raw('CAST(SUM(poin_negatif) AS SIGNED) as total_minus'),
                DB::raw('COUNT(*) as total_catatan')
            )->first();
    }

    private function buildQuery($siswaId, $semesterId, Request $request)
    {
        // Pastikan hanya mengambil poin milik siswa tersebut
        $query = PoinSiswa::query()->where('siswa_id', $siswaId);
        
        // Filter berdasarkan semester_id jika tersedia
        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        $this->applyFilters($query, $request);
        return $query;
    }

    private function getPaginator($query, $semesterId, Request $request)
    {
        $perPage = min((int) $request->get('per_page', 10), 100);
        return $query->with([
            'guruStaf', 
            'semester.tahunAjaran', 
            'siswa.riwayatKelas' => function($q) use ($semesterId) {
                $q->where('semester_id', $semesterId)->with('kelas');
            }
        ])->latest()->paginate($perPage);
    }

    private function getKelasPeriode($siswa, $semesterId)
    {
        if (!$siswa) return null;

        return $siswa->riwayatKelas()
            ->where('semester_id', $semesterId)
            ->with('kelas')
            ->first();
    }

    private function mapData($paginator)
    {
        return collect($paginator->items())->map(fn($item) => [
            'id'         => $item->id,
            'tanggal'    => Carbon::parse($item->tanggal)->format('d-m-Y'),
            'positif'    => (int)$item->poin_positif,
            'negatif'    => (int)$item->poin_negatif,
            'keterangan' => $item->indikator ?? $item->keterangan,
            'pelapor'    => $item->guruStaf?->nama ?? 'Sistem',
        ]);
    }

    private function validateSiswa($siswa)
    {
        // Tambahkan pengecekan explicit is_active
        if (!$siswa || $siswa->is_active !== true) {
            return response()->json([
                'success' => false,
                'message' => 'Data siswa tidak ditemukan atau akun sudah tidak aktif.'
            ], Response::HTTP_NOT_FOUND);
        }
        return null;
    }

    private function applyFilters($query, Request $request)
    {
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('keterangan', 'like', "%{$search}%")
                  ->orWhereHas('guruStaf', fn($qg) => $qg->where('nama', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('mulai_tanggal') && $request->filled('sampai_tanggal')) {
            $query->whereBetween('tanggal', [$request->mulai_tanggal, $request->sampai_tanggal]);
        }

        if ($request->filled('jenis')) {
            if ($request->jenis === 'negatif') $query->where('poin_negatif', '>', 0);
            elseif ($request->jenis === 'positif') $query->where('poin_positif', '>', 0);
        }
    }

    private function formatPagination($paginator)
    {
        $data = $paginator->toArray();
        return [
            'current_page'  => $data['current_page'] ?? 1,
            'last_page'     => $data['last_page'] ?? 1,
            'per_page'      => $data['per_page'] ?? 10,
            'total'         => $data['total'] ?? 0,
            'next_page_url' => $data['next_page_url'] ?? null,
            'prev_page_url' => $data['prev_page_url'] ?? null,
        ];
    }

    public function show(PoinSiswa $poinSiswa): JsonResponse
    {
        try {
            $this->authorize('view', $poinSiswa);

            // Pastikan relasi semester dan tahun ajaran dimuat
            $poinSiswa->load(['guruStaf', 'semester.tahunAjaran']);

            return response()->json([
                'success' => true,
                'data' => [
                    'id'           => $poinSiswa->id,
                    'tanggal'      => Carbon::parse($poinSiswa->tanggal)->format('d-m-Y'),
                    'poin_positif' => (int)$poinSiswa->poin_positif,
                    'poin_negatif' => (int)$poinSiswa->poin_negatif,
                    'keterangan'   => $poinSiswa->indikator ?? $poinSiswa->keterangan,
                    'pelapor'      => $poinSiswa->guruStaf?->nama_lengkap ?? $poinSiswa->guruStaf?->nama ?? 'Sistem',
                    'tahun_ajaran' => $poinSiswa->semester?->tahunAjaran?->nama ?? '-',
                    'semester'     => $poinSiswa->semester?->nama ?? '-',
                    'created_at'   => $poinSiswa->created_at->format('d-m-Y H:i'),
                ],
            ], Response::HTTP_OK);
            
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data poin tidak ditemukan atau akses dilarang.',
            ], Response::HTTP_FORBIDDEN);
        }
    }
}