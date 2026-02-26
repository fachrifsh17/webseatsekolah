<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\{PoinSiswa, TahunAjaran};
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

            $siswa = Auth::user()->siswa;
            if ($error = $this->validateSiswa($siswa)) return $error;

            $tahunAktif = TahunAjaran::where('is_active', true)->first();
            $tahunAjaranId = $request->get('tahun_ajaran_id', $tahunAktif?->id);

            $summaryData = $this->getSummaryAkumulatif($siswa->id);
            $query = $this->buildQuery($siswa->id, $tahunAjaranId, $request);
            
            $tahunTampil = $tahunAjaranId ? TahunAjaran::find($tahunAjaranId) : $tahunAktif;
            $paginator = $this->getPaginator($query, $tahunAjaranId, $request);
            $kelasPeriode = $this->getKelasPeriode($siswa, $tahunAjaranId);

            return response()->json([
                'success' => true,
                'message' => 'Data poin berhasil diambil.',
                'header'  => [
                    'nama'         => $siswa->nama_lengkap,
                    'nis'          => $siswa->nis,
                    'kelas'        => $kelasPeriode?->kelas?->nama_kelas ?? '-',
                    'tahun_ajaran' => $tahunTampil?->nama,
                    'semester'     => $tahunTampil?->semester,
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

    private function buildQuery($siswaId, $tahunAjaranId, Request $request)
    {
        $query = PoinSiswa::query()->where('siswa_id', $siswaId);
        
        if ($tahunAjaranId) {
            $query->where('tahun_ajaran_id', $tahunAjaranId);
        }

        $this->applyFilters($query, $request);
        return $query;
    }

    private function getPaginator($query, $tahunAjaranId, Request $request)
    {
        $perPage = min((int) $request->get('per_page', 10), 100);
        return $query->with([
            'guruStaf', 
            'tahunAjaran', 
            'siswa.riwayatKelas' => fn($q) => $q->where('tahun_ajaran_id', $tahunAjaranId)->with('kelas')
        ])->latest()->paginate($perPage);
    }

    private function getKelasPeriode($siswa, $tahunAjaranId)
    {
        return $siswa->riwayatKelas()
            ->where('tahun_ajaran_id', $tahunAjaranId)
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
            'pelapor'    => $item->guruStaf?->nama,
        ]);
    }

    private function validateSiswa($siswa)
    {
        if (!$siswa || !$siswa->is_active) {
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
            'current_page'  => $data['current_page'],
            'last_page'     => $data['last_page'],
            'per_page'      => $data['per_page'],
            'total'         => $data['total'],
            'next_page_url' => $data['next_page_url'],
            'prev_page_url' => $data['prev_page_url'],
        ];
    }

    public function show(PoinSiswa $poinSiswa): JsonResponse
    {
        try {
            $this->authorize('view', $poinSiswa);

            return response()->json([
                'success' => true,
                'data' => [
                    'id'           => $poinSiswa->id,
                    'tanggal'      => Carbon::parse($poinSiswa->tanggal)->format('d-m-Y'),
                    'poin_positif' => (int)$poinSiswa->poin_positif,
                    'poin_negatif' => (int)$poinSiswa->poin_negatif,
                    'keterangan'   => $poinSiswa->indikator ?? $poinSiswa->keterangan,
                    'pelapor'      => $poinSiswa->guruStaf?->nama_lengkap ?? $poinSiswa->guruStaf?->nama,
                    'tahun_ajaran' => $poinSiswa->tahunAjaran?->nama,
                    'semester'     => $poinSiswa->tahunAjaran?->semester,
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