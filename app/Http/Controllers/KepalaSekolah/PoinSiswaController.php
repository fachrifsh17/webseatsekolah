<?php

namespace App\Http\Controllers\KepalaSekolah;

use App\Http\Controllers\Controller;
use App\Models\PoinSiswa;
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

    public function index(Request $request): JsonResponse
    {
        try {
            $query = PoinSiswa::with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])
                ->whereHas('siswa', function ($q) {
                    $q->where('is_active', true);
                });

            if ($request->filled('siswa_id')) {
                $query->where('siswa_id', $request->siswa_id);
            }

            if ($request->filled('tahun_ajaran_id')) {
                $query->where('tahun_ajaran_id', $request->tahun_ajaran_id);
            }

            if ($request->filled('bulan')) {
                $date = Carbon::parse($request->bulan);
                $query->whereMonth('tanggal', $date->month)
                      ->whereYear('tanggal', $date->year);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('siswa', function ($q) use ($search) {
                    $q->where('nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('nisn', 'like', "%{$search}%");
                });
            }

            $query->orderByDesc('tanggal')->orderByDesc('created_at');

            $summaryGlobal = null;
            if ($request->filled('siswa_id')) {
                $summaryGlobal = DB::table('poin_siswa')
                    ->where('siswa_id', $request->siswa_id)
                    ->select(
                        DB::raw('SUM(poin_positif) as total_plus'),
                        DB::raw('SUM(poin_negatif) as total_minus'),
                        DB::raw('SUM(poin_positif) - SUM(poin_negatif) as saldo_poin')
                    )->first();
            }

            $perPage = $request->get('per_page', 20);
            $data = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'summary_kumulatif' => $summaryGlobal,
                'data'    => PoinSiswaResource::collection($data),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'per_page'     => (int) $data->perPage(),
                    'total'        => $data->total(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Kepala Sekolah Index Poin Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data poin.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $poin = PoinSiswa::with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data'    => new PoinSiswaResource($poin)
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
            $profil = DB::table('profil_sekolah')->first();
            $kontak = DB::table('data_kontak')->first();

            $query = PoinSiswa::with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])
                ->whereHas('siswa', function ($q) {
                    $q->where('is_active', true);
                })
                ->orderBy('tanggal', 'asc');

            if ($request->filled('kelas_id')) {
                $query->whereHas('siswa', function ($q) use ($request) {
                    $q->where('kelas_id', $request->kelas_id);
                });
            }

            if ($request->filled('tahun_ajaran_id')) {
                $query->where('tahun_ajaran_id', $request->tahun_ajaran_id);
            }

            if ($request->filled('bulan')) {
                $date = Carbon::parse($request->bulan);
                $query->whereMonth('tanggal', $date->month)
                      ->whereYear('tanggal', $date->year);
                $labelWaktu = $date->translatedFormat('F Y');
            } else {
                $labelWaktu = "Seluruh Periode (Kumulatif)";
            }

            $namaKelas = $request->nama_kelas ?? 'Seluruh Siswa';

            return Excel::download(
                new PoinSiswaExport($query, $namaKelas, $labelWaktu, $profil, $kontak),
                "Laporan_Kepsek_Poin_Siswa_" . now()->format('YmdHis') . ".xlsx"
            );
        } catch (Throwable $e) {
            Log::error('Kepala Sekolah Export Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengekspor data'], 500);
        }
    }
}