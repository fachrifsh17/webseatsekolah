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
            
            // 1. Ambil semester aktif sebagai default, tapi tetap terima semester_id dari request
            $semesterAktif = Semester::where('is_active', true)->first();
            $semesterId = $request->query('semester_id', $semesterAktif?->id);

            // 2. Ambil data anak (menggunakan semesterId untuk menentukan kelas/riwayat mereka)
            $children = $this->getChildren($user, $semesterId);
            $childrenIds = $children->pluck('id')->toArray();

            if (empty($childrenIds)) {
                return $this->emptyResponse($semesterAktif);
            }

            // 3. Bangun Query untuk LIST (Tetap difilter per semester agar history tidak menumpuk)
            $query = $this->buildQuery($childrenIds, $semesterId, $request);
            if ($query instanceof JsonResponse) {
                return $query;
            }

            // 4. Hitung Summary KESELURUHAN (Kumulatif lintas semester)
            // Filter semesterId tidak dikirim ke sini agar SUM menghitung semua data di database
            $summaryData = $this->getSummaryKeseluruhan($childrenIds, $request);
            
            $semesterTampil = $semesterId ? Semester::with('tahunAjaran')->find($semesterId) : $semesterAktif;
            $perPage = min((int) $request->query('per_page', 10), 100);
            
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
        return Siswa::where('is_active', true)
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

        // Filter list catatan tetap berdasarkan semester agar tampilan per halaman rapi
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

    /**
     * PERBAIKAN: Fungsi ini sekarang tidak menerima $semesterId
     * agar SUM menghitung seluruh poin dari awal siswa masuk.
     */
    private function getSummaryKeseluruhan(array $childrenIds, Request $request)
    {
        $query = PoinSiswa::whereIn('siswa_id', $childrenIds);

        // Jika orang tua memilih satu anak, hitung akumulasi anak tersebut saja (seluruh semester)
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
                'kelas'      => $item->siswa?->riwayatKelas->where('semester_id', $semesterId)->first()?->kelas?->nama_kelas ?? '-',
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
    
    // ... Method show dan emptyResponse tetap sama ...
}