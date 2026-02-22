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

            $summaryData = $this->getSummary($query);
            $tahunTampil = $tahunAjaranId ? TahunAjaran::find($tahunAjaranId) : $tahunAktif;
            $perPage = min((int) $request->get('per_page', 10), 100);
            $data = $query->with(['siswa.kelas', 'guruStaf'])->latest()->paginate($perPage);

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
        return Siswa::whereHas('orangtua', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->where('is_active', true)
            ->whereHas('kelas', function($q) use ($tahunAjaranId) {
                $q->where('is_active', true)->where('tahun_ajaran_id', $tahunAjaranId);
            })
            ->with('kelas:id,nama_kelas')
            ->get(['id', 'nama_lengkap', 'kelas_id', 'nis']);
    }

    private function emptyResponse($tahunAktif)
    {
        return response()->json([
            'success' => true,
            'message' => 'Data anak pada tahun ajaran aktif tidak ditemukan.',
            'header'  => [
                'tahun_ajaran' => $tahunAktif?->nama,
                'semester'     => $tahunAktif?->semester,
            ],
            'summary' => [
                'total_positif' => 0,
                'total_negatif' => 0,
                'total_catatan' => 0,
            ],
            'list_anak' => [],
            'data'      => [],
            'meta'      => [
                'total' => 0
            ]
        ], Response::HTTP_OK);
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

    private function getSummary($query)
    {
        return (clone $query)->select(
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
                'total_positif' => $summaryData->total_plus ?? 0,
                'total_negatif' => $summaryData->total_minus ?? 0,
                'total_catatan' => $summaryData->total_catatan ?? 0,
            ],
            'list_anak' => $children->map(fn($item) => [
                'id'    => $item->id,
                'nama'  => $item->nama_lengkap,
                'kelas' => $item->kelas?->nama_kelas,
                'nis'   => $item->nis
            ]),
            'data' => collect($data->items())->map(function($item) {
                return [
                    'id'         => $item->id,
                    'nama_siswa' => $item->siswa?->nama_lengkap,
                    'kelas'      => $item->siswa?->kelas?->nama_kelas,
                    'tanggal'    => Carbon::parse($item->tanggal)->format('d-m-Y'),
                    'positif'    => (int)$item->poin_positif,
                    'negatif'    => (int)$item->poin_negatif,
                    'keterangan' => $item->keterangan ?? $item->indikator,
                    'pelapor'    => $item->guruStaf?->nama ?? 'Sistem',
                ];
            }),
            'meta' => [
                'current_page'  => $paginationData['current_page'],
                'last_page'     => $paginationData['last_page'],
                'per_page'      => $paginationData['per_page'],
                'total'         => $paginationData['total'],
                'from'          => $paginationData['from'],
                'to'            => $paginationData['to'],
                'path'          => $paginationData['path'],
                'next_page_url' => $paginationData['next_page_url'],
                'prev_page_url' => $paginationData['prev_page_url'],
                'links'         => array_map(function ($link) {
                    return [
                        'url'    => $link['url'],
                        'label'  => $link['label'],
                        'page'   => is_numeric($link['label']) ? (int) $link['label'] : null,
                        'active' => $link['active'],
                    ];
                }, $paginationData['links']),
            ],
        ], Response::HTTP_OK);
    }

    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $poinSiswa = PoinSiswa::with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])->findOrFail($id);

            $isValid = Siswa::where('id', $poinSiswa->siswa_id)
                ->whereHas('orangtua', function($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->whereHas('kelas', function($q) use ($poinSiswa) {
                    $q->where('tahun_ajaran_id', $poinSiswa->tahun_ajaran_id);
                })
                ->exists();

            if (!$isValid) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Akses dilarang atau data tidak relevan dengan periode aktif.'
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
}