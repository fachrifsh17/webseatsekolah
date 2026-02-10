<?php

namespace App\Http\Controllers\KepalaSekolah;

use App\Http\Controllers\Controller;
use App\Models\{PoinSiswa, TahunAjaran};
use App\Http\Resources\PoinSiswaResource;
use App\Exports\PoinSiswaExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log};
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class PoinSiswaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
    }

    /**
     * Helper untuk mengambil data poin dengan subquery kumulatif
     * Sesuai dengan struktur Admin
     */
    private function getPoinWithKumulatif($id)
    {
        return PoinSiswa::with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])
            ->select('poin_siswa.*')
            ->addSelect([
                'total_kumulatif_positif' => DB::table('poin_siswa as ps')
                    ->whereColumn('ps.siswa_id', 'poin_siswa.siswa_id')
                    ->selectRaw('SUM(poin_positif)'),
                'total_kumulatif_negatif' => DB::table('poin_siswa as ps')
                    ->whereColumn('ps.siswa_id', 'poin_siswa.siswa_id')
                    ->selectRaw('SUM(poin_negatif)')
            ])
            ->findOrFail($id);
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = PoinSiswa::with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])
                ->select('poin_siswa.*')
                ->addSelect([
                    'total_kumulatif_positif' => DB::table('poin_siswa as ps')
                        ->whereColumn('ps.siswa_id', 'poin_siswa.siswa_id')
                        ->selectRaw('SUM(poin_positif)'),
                    'total_kumulatif_negatif' => DB::table('poin_siswa as ps')
                        ->whereColumn('ps.siswa_id', 'poin_siswa.siswa_id')
                        ->selectRaw('SUM(poin_negatif)')
                ])
                ->whereHas('siswa', function ($q) {
                    $q->where('is_active', true)
                      ->whereHas('kelas', fn($qk) => $qk->where('is_active', true));
                });

            // Filter Tahun Ajaran (Default ke yang aktif jika tidak diisi)
            if ($request->filled('tahun_ajaran_id')) {
                $query->where('tahun_ajaran_id', $request->tahun_ajaran_id);
            } else {
                $query->whereHas('tahunAjaran', fn($q) => $q->where('is_active', true));
            }

            if ($request->filled('siswa_id')) {
                $query->where('siswa_id', $request->siswa_id);
            }

            if ($request->filled('bulan')) {
                $time = strtotime($request->bulan);
                $query->whereMonth('tanggal', date('m', $time))
                      ->whereYear('tanggal', date('Y', $time));
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('siswa', fn($q) => 
                    $q->where('nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('nisn', 'like', "%{$search}%")
                );
            }

            $perPage = min((int) $request->get('per_page', 20), 100);
            
            // Urutan disamakan dengan Admin: Negatif terbanyak dulu baru tanggal terbaru
            $data = $query->orderByDesc('total_kumulatif_negatif')
                          ->orderByDesc('tanggal')
                          ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => PoinSiswaResource::collection($data),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'total'        => $data->total(),
                ],
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Kepsek Poin Index Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data poin.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => new PoinSiswaResource($this->getPoinWithKumulatif($id))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan.'
            ], Response::HTTP_NOT_FOUND);
        }
    }

    public function export(Request $request)
    {
        try {
            $profil = DB::table('profil_sekolah')->first() ?? DB::table('sekolah_setting')->first();
            $kontak = DB::table('data_kontak')->first();

            $namaKelas = $request->nama_kelas ?? 'Seluruh_Siswa';
            $namaKelasFile = str_replace([' ', '/', '\\'], '_', $namaKelas);
            
            $labelWaktu = $request->filled('bulan') ? date('F Y', strtotime($request->bulan)) : "Kumulatif";
            $bulanFile = $request->filled('bulan') ? date('M_Y', strtotime($request->bulan)) : "Semua_Waktu";

            $query = PoinSiswa::with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])
                ->whereHas('siswa', function ($q) {
                    $q->where('is_active', true)
                      ->whereHas('kelas', fn($qk) => $qk->where('is_active', true));
                });

            if ($request->filled('kelas_id')) {
                $query->whereHas('siswa', fn($q) => $q->where('kelas_id', $request->kelas_id));
            }

            if ($request->filled('tahun_ajaran_id')) {
                $query->where('tahun_ajaran_id', $request->tahun_ajaran_id);
            } else {
                $query->whereHas('tahunAjaran', fn($q) => $q->where('is_active', true));
            }

            $fileName = "Rekap_Poin_Kepsek_{$namaKelasFile}_{$bulanFile}_" . date('His') . ".xlsx";

            return Excel::download(
                new PoinSiswaExport($query->orderBy('tanggal', 'asc'), $namaKelas, $labelWaktu, $profil, $kontak),
                $fileName
            );
        } catch (Throwable $e) {
            Log::error('Kepsek Poin Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal mengekspor data.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}