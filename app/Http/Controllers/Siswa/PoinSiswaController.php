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

            // Ambil data siswa dari user yang login
            $siswa = Auth::user()->siswa;
            if ($error = $this->validateSiswa($siswa)) return $error;

            $query = PoinSiswa::query()->where('siswa_id', $siswa->id);
            $this->applyFilters($query, $request);

            // Hitung summary berdasarkan query yang sudah difilter
            $summaryData = (clone $query)->select(
                DB::raw('CAST(SUM(poin_positif) AS SIGNED) as total_plus'),
                DB::raw('CAST(SUM(poin_negatif) AS SIGNED) as total_minus'),
                DB::raw('COUNT(*) as total_catatan')
            )->first();

            // Ambil info tahun ajaran untuk header
            $tahunTampil = $request->filled('tahun_ajaran_id') 
                ? TahunAjaran::find($request->tahun_ajaran_id) 
                : TahunAjaran::where('is_active', true)->first();
            
            $perPage = min((int) $request->get('per_page', 10), 100);
            
            // PENYESUAIAN: Menggunakan riwayatKelas yang difilter is_active
            $paginator = $query->with([
                                    'guruStaf', 
                                    'tahunAjaran', 
                                    'siswa.riwayatKelas' => fn($q) => $q->where('is_active', true)->with('kelas')
                                ])
                                ->latest()
                                ->paginate($perPage);

            // PENYESUAIAN: Mengambil nama kelas dari koleksi riwayatKelas yang aktif
            $kelasAktif = $siswa->riwayatKelas()->where('is_active', true)->with('kelas')->first();

            return response()->json([
                'success' => true,
                'message' => 'Data poin berhasil diambil.',
                'header'  => [
                    'nama'         => $siswa->nama_lengkap,
                    'nis'          => $siswa->nis,
                    // PENYESUAIAN: Ambil dari riwayatKelas pertama yang aktif
                    'kelas'        => $kelasAktif?->kelas?->nama_kelas ?? '-',
                    'tahun_ajaran' => $tahunTampil?->nama,
                    'semester'     => $tahunTampil?->semester,
                ],
                'summary' => [
                    'total_positif' => $summaryData->total_plus ?? 0,
                    'total_negatif' => $summaryData->total_minus ?? 0,
                    'total_catatan' => $summaryData->total_catatan ?? 0,
                ],
                'data' => collect($paginator->items())->map(fn($item) => [
                    'id'         => $item->id,
                    'tanggal'    => Carbon::parse($item->tanggal)->format('d-m-Y'),
                    'positif'    => (int)$item->poin_positif,
                    'negatif'    => (int)$item->poin_negatif,
                    'keterangan' => $item->indikator ?? $item->keterangan,
                    'pelapor'    => $item->guruStaf?->nama,
                ]),
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
        if ($request->filled('tahun_ajaran_id')) {
            $query->where('tahun_ajaran_id', $request->tahun_ajaran_id);
        }

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
            'from'          => $data['from'],
            'to'            => $data['to'],
            'path'          => $data['path'],
            'next_page_url' => $data['next_page_url'],
            'prev_page_url' => $data['prev_page_url'],
            'links'         => array_map(fn($link) => [
                'url'    => $link['url'],
                'label'  => $link['label'],
                'page'   => is_numeric($link['label']) ? (int) $link['label'] : null,
                'active' => $link['active'],
            ], $data['links']),
        ];
    }

    public function show(PoinSiswa $poinSiswa): JsonResponse
    {
        try {
            $this->authorize('view', $poinSiswa);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $poinSiswa->id,
                    'tanggal' => Carbon::parse($poinSiswa->tanggal)->format('d-m-Y'),
                    'poin_positif' => (int)$poinSiswa->poin_positif,
                    'poin_negatif' => (int)$poinSiswa->poin_negatif,
                    'keterangan' => $poinSiswa->indikator ?? $poinSiswa->keterangan,
                    'pelapor' => $poinSiswa->guruStaf?->nama_lengkap ?? $poinSiswa->guruStaf?->nama,
                    'tahun_ajaran' => $poinSiswa->tahunAjaran?->nama,
                    'semester' => $poinSiswa->tahunAjaran?->semester,
                    'created_at' => $poinSiswa->created_at->format('d-m-Y H:i'),
                ],
            ], Response::HTTP_OK);
            
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data poin tidak ditemukan atau akses dilarang.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_FORBIDDEN);
        }
    }
}