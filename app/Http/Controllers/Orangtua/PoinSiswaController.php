<?php

namespace App\Http\Controllers\Orangtua;

use App\Http\Controllers\Controller;
use App\Models\{PoinSiswa, Semester, Siswa};
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
            // Menggunakan Semester sesuai konteks sebelumnya
            $semesterAktif = Semester::where('is_active', true)->first();
            $semesterId = $request->get('semester_id', $semesterAktif?->id);

            // 1. Ambil data anak-anak yang aktif
            $children = $this->getChildren($user, $semesterId);
            $childrenIds = $children->pluck('id')->toArray();

            if (empty($childrenIds)) {
                return $this->emptyResponse($semesterAktif);
            }

            // 2. Bangun Query
            $query = $this->buildQuery($childrenIds, $semesterId, $request);
            if ($query instanceof JsonResponse) {
                return $query;
            }

            // 3. Summary & Paginator
            $summaryData = $this->getSummaryKeseluruhan($childrenIds, $request, $semesterId);
            $semesterTampil = $semesterId ? Semester::with('tahunAjaran')->find($semesterId) : $semesterAktif;
            $perPage = min((int) $request->get('per_page', 10), 100);
            
            $data = $query->with([
                'siswa.riwayatKelas' => fn($q) => $q->where('semester_id', $semesterId)->with('kelas'),
                'guruStaf',
                'semester.tahunAjaran'
            ])->latest()->paginate($perPage);

            return $this->formatResponse($data, $summaryData, $children, $semesterTampil, $semesterId);
        } catch (Throwable $e) {
            Log::error('Gagal mengambil poin orangtua: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data poin.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function getChildren($user, $semesterId)
    {
        return Siswa::where('is_active', true) // Filter hanya siswa aktif
            ->whereHas('orangtua', fn($q) => $q->where('user_id', $user->id))
            ->whereHas('riwayatKelas', function($q) use ($semesterId) {
                $q->where('semester_id', $semesterId);
            })
            ->with(['riwayatKelas' => fn($q) => $q->where('semester_id', $semesterId)->with('kelas')])
            ->get(['id', 'nama_lengkap', 'nis']);
    }

    private function buildQuery(array $childrenIds, $semesterId, Request $request)
    {
        $query = PoinSiswa::query()->whereIn('siswa_id', $childrenIds);

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        if ($request->filled('siswa_id')) {
            if (!in_array($request->siswa_id, $childrenIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak atau siswa tidak terdaftar di periode ini.'
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

    private function getSummaryKeseluruhan(array $childrenIds, Request $request, $semesterId)
    {
        $query = PoinSiswa::whereIn('siswa_id', $childrenIds);

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        if ($request->filled('siswa_id')) {
            $query->where('siswa_id', $request->siswa_id);
        }

        return $query->select(
            DB::raw('CAST(SUM(poin_positif) AS SIGNED) as total_plus'),
            DB::raw('CAST(SUM(poin_negatif) AS SIGNED) as total_minus'),
            DB::raw('COUNT(*) as total_catatan')
        )->first();
    }

    private function formatResponse($data, $summaryData, $children, $semesterTampil, $semesterId)
    {
        $paginationData = $data->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Data poin berhasil diambil.',
            'header' => [
                'tahun_ajaran' => $semesterTampil?->tahunAjaran?->nama ?? '-',
                'semester'     => $semesterTampil?->nama ?? '-',
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
                'per_page'     => $paginationData['per_page'],
            ],
        ], Response::HTTP_OK);
    }

    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $poinSiswa = PoinSiswa::with(['siswa', 'guruStaf', 'semester.tahunAjaran'])->findOrFail($id);

            // Validasi: Apakah poin ini milik salah satu anak dari orang tua yang login
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
                    'keterangan'   => $poinSiswa->keterangan ?? $poinSiswa->indikator,
                    'pelapor'      => $poinSiswa->guruStaf?->nama ?? 'Sistem',
                    'tahun_ajaran' => $poinSiswa->semester?->tahunAjaran?->nama ?? '-',
                    'semester'     => $poinSiswa->semester?->nama ?? '-',
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

    private function emptyResponse($semesterAktif)
    {
        return response()->json([
            'success' => true,
            'message' => 'Data tidak ditemukan.',
            'header'  => [
                'tahun_ajaran' => $semesterAktif?->tahunAjaran?->nama, 
                'semester'     => $semesterAktif?->nama
            ],
            'summary'   => ['total_positif' => 0, 'total_negatif' => 0, 'total_catatan' => 0, 'saldo_poin' => 0],
            'list_anak' => [],
            'data'      => [],
            'meta'      => ['total' => 0]
        ], Response::HTTP_OK);
    }
}