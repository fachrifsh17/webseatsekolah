<?php

namespace App\Http\Controllers\Orangtua;

use App\Http\Controllers\Controller;
use App\Models\{PoinSiswa, TahunAjaran, Siswa};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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
        $this->middleware('role:Orangtua');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tahunAktif = TahunAjaran::where('is_active', true)->first();
            $tahunAjaranId = $request->get('tahun_ajaran_id', $tahunAktif?->id);

            $children = $this->getChildren($user, $tahunAjaranId);
            $childrenIds = $children->pluck('id')->toArray();

            if (empty($childrenIds)) {
                return $this->emptyResponse($tahunAktif);
            }

            $query = $this->buildQuery($childrenIds, $tahunAjaranId, $request);
            if ($query instanceof JsonResponse) {
                return $query;
            }

            $summaryData = $this->getSummaryKeseluruhan($childrenIds, $request);
            
            $tahunTampil = $tahunAjaranId ? TahunAjaran::find($tahunAjaranId) : $tahunAktif;
            $perPage = min((int) $request->get('per_page', 10), 100);
            
            $data = $query->with([
                'siswa.riwayatKelas' => fn($q) => $q->where('tahun_ajaran_id', $tahunAjaranId)->with('kelas'),
                'guruStaf'
            ])->latest()->paginate($perPage);

            return $this->formatResponse($data, $summaryData, $children, $tahunTampil);
        } catch (Throwable $e) {
            Log::error('Gagal mengambil poin orangtua: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data poin.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function getChildren($user, $tahunAjaranId)
    {
        return Siswa::whereHas('orangtua', fn($q) => $q->where('user_id', $user->id))
            ->whereHas('riwayatKelas', function($q) use ($tahunAjaranId) {
                $q->where('tahun_ajaran_id', $tahunAjaranId)->where('is_active', true);
            })
            ->with(['riwayatKelas' => fn($q) => $q->where('tahun_ajaran_id', $tahunAjaranId)->with('kelas')])
            ->get(['id', 'nama_lengkap', 'nis']);
    }

    private function buildQuery(array $childrenIds, $tahunAjaranId, Request $request)
    {
        $query = PoinSiswa::query()->whereIn('siswa_id', $childrenIds);

        if ($tahunAjaranId) {
            $query->where('tahun_ajaran_id', $tahunAjaranId);
        }

        if ($request->filled('siswa_id')) {
            if (!in_array($request->siswa_id, $childrenIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak atau siswa tidak aktif di periode ini.'
                ], Response::HTTP_FORBIDDEN);
            }
            $query->where('siswa_id', $request->siswa_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('keterangan', 'like', "%{$search}%")
                  ->orWhereHas('siswa', fn($qs) => $qs->where('nama_lengkap', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('jenis')) {
            if ($request->jenis === 'negatif') {
                $query->where('poin_negatif', '>', 0);
            } elseif ($request->jenis === 'positif') {
                $query->where('poin_positif', '>', 0);
            }
        }

        return $query;
    }

    private function getSummaryKeseluruhan(array $childrenIds, Request $request)
    {
        $query = PoinSiswa::whereIn('siswa_id', $childrenIds);

        if ($request->filled('siswa_id')) {
            $query->where('siswa_id', $request->siswa_id);
        }

        return $query->select(
            DB::raw('CAST(SUM(poin_positif) AS SIGNED) as total_plus'),
            DB::raw('CAST(SUM(poin_negatif) AS SIGNED) as total_minus'),
            DB::raw('COUNT(*) as total_catatan')
        )->first();
    }

    private function formatResponse($data, $summaryData, $children, $tahunTampil)
    {
        $paginationData = $data->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Data poin berhasil diambil.',
            'header' => [
                'tahun_ajaran' => $tahunTampil?->nama,
                'semester'     => $tahunTampil?->semester,
            ],
            'summary' => [
                'total_positif' => (int)($summaryData->total_plus ?? 0),
                'total_negatif' => (int)($summaryData->total_minus ?? 0),
                'total_catatan' => (int)($summaryData->total_catatan ?? 0),
                'saldo_poin'    => (int)($summaryData->total_plus ?? 0) - (int)($summaryData->total_minus ?? 0),
            ],
            'list_anak' => $children->map(fn($item) => [
                'id'    => $item->id,
                'nama'  => $item->nama_lengkap,
                'kelas' => $item->riwayatKelas->first()?->kelas?->nama_kelas ?? '-',
                'nis'   => $item->nis
            ]),
            'data' => collect($data->items())->map(fn($item) => [
                'id'         => $item->id,
                'nama_siswa' => $item->siswa?->nama_lengkap,
                'kelas'      => $item->siswa?->riwayatKelas->first()?->kelas?->nama_kelas ?? '-',
                'tanggal'    => Carbon::parse($item->tanggal)->format('d-m-Y'),
                'positif'    => (int)$item->poin_positif,
                'negatif'    => (int)$item->poin_negatif,
                'keterangan' => $item->keterangan ?? $item->indikator,
                'pelapor'    => $item->guruStaf?->nama ?? 'Sistem',
            ]),
            'meta' => [
                'current_page' => $paginationData['current_page'],
                'last_page'    => $paginationData['last_page'],
                'total'        => $paginationData['total'],
            ],
        ], Response::HTTP_OK);
    }

    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $poinSiswa = PoinSiswa::with(['siswa', 'guruStaf', 'tahunAjaran'])->findOrFail($id);

            $isValid = Siswa::where('id', $poinSiswa->siswa_id)
                ->whereHas('orangtua', fn($q) => $q->where('user_id', $user->id))
                ->exists();

            if (!$isValid) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Akses dilarang.'
                ], Response::HTTP_FORBIDDEN);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id'           => $poinSiswa->id,
                    'nama_siswa'   => $poinSiswa->siswa?->nama_lengkap,
                    'tanggal'      => Carbon::parse($poinSiswa->tanggal)->format('d-m-Y'),
                    'poin_positif' => (int)$poinSiswa->poin_positif,
                    'poin_negatif' => (int)$poinSiswa->poin_negatif,
                    'keterangan'   => $poinSiswa->keterangan,
                    'pelapor'      => $poinSiswa->guruStaf?->nama,
                    'tahun_ajaran' => $poinSiswa->tahunAjaran?->nama,
                    'semester'     => $poinSiswa->tahunAjaran?->semester,
                    'created_at'   => $poinSiswa->created_at->format('d-m-Y H:i'),
                ],
            ], Response::HTTP_OK);
            
        } catch (Throwable $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Detail poin tidak ditemukan.'
            ], Response::HTTP_NOT_FOUND);
        }
    }

    private function emptyResponse($tahunAktif)
    {
        return response()->json([
            'success' => true,
            'message' => 'Data tidak ditemukan.',
            'header'  => ['tahun_ajaran' => $tahunAktif?->nama, 'semester' => $tahunAktif?->semester],
            'summary' => ['total_positif' => 0, 'total_negatif' => 0, 'total_catatan' => 0],
            'list_anak' => [],
            'data'      => [],
            'meta'      => ['total' => 0]
        ], Response::HTTP_OK);
    }
}