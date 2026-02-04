<?php

namespace App\Http\Controllers\KepalaSekolah;

use App\Http\Controllers\Controller;
use App\Models\Presensi;
use App\Models\Siswa;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Http\Resources\PresensiResource;
use App\Exports\PresensiExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class PresensiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            if (!$request->filled(['kelas_id', 'bulan', 'tahun']) && !$request->filled('tanggal')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Silakan pilih kelas dan filter waktu (bulan/tahun atau tanggal) terlebih dahulu.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $query = Presensi::query();
            
            $query->whereHas('siswa', fn($q) => $q->where('is_active', true));

            $query = $this->applyPresensiFilters($request, $query);

            $perPage = min((int) $request->get('per_page', 50), 100);
            $data = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => PresensiResource::collection($data),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'per_page'     => (int) $data->perPage(),
                    'total'        => $data->total(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Kesiswaan Presensi Index Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal mengambil data.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        try {
            if (!$request->filled(['kelas_id', 'bulan', 'tahun'])) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Silakan pilih kelas, bulan, dan tahun untuk mengekspor data.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $profil = DB::table('sekolah_setting')->first();
            $kontak = DB::table('data_kontak')->first();
            
            $query = Presensi::query();
            $filteredQuery = $this->applyPresensiFilters($request, $query);

            $date = Carbon::createFromDate($request->tahun, $request->bulan, 1);
            $labelWaktu = $date->translatedFormat('F Y');

            $taId = $request->get('tahun_ajaran_id') ?? TahunAjaran::where('is_active', true)->first()?->id;
            $taData = DB::table('tahun_ajaran')->where('id', $taId)->first();
            $tahunAjaranLabel = $taData ? $taData->nama . " (" . $taData->semester . ")" : '-';

            $dataKelas = Kelas::with('waliKelas')->find($request->kelas_id);
            $namaKelas = $dataKelas ? $dataKelas->nama_kelas : "Semua Kelas";

            return Excel::download(
                new PresensiExport($filteredQuery, $namaKelas, $labelWaktu, $profil, $kontak, $dataKelas, 'kesiswaan', $tahunAjaranLabel), 
                'Rekap_Presensi_Kesiswaan_' . now()->format('YmdHis') . '.xlsx'
            );
        } catch (Throwable $e) {
            Log::error('Kesiswaan Export Presensi Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal mengekspor data'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function applyPresensiFilters(Request $request, $query)
    {
        $taId = $request->tahun_ajaran_id ?? TahunAjaran::where('is_active', true)->first()?->id;
        if ($taId) $query->where('tahun_ajaran_id', (string) $taId);

        if ($request->filled('semester')) {
            $query->whereHas('tahunAjaran', fn($q) => $q->where('semester', $request->semester));
        }

        if ($request->filled('kelas_id')) {
            $query->whereHas('siswa', fn($q) => $q->where('kelas_id', (string) $request->kelas_id));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('siswa', fn($qs) => $qs->where('nama_lengkap', 'like', "%{$search}%"))
                  ->orWhere('keterangan', 'like', "%{$search}%");
            });
        }

        if ($request->filled('bulan') && $request->filled('tahun')) {
            $query->whereMonth('tanggal', $request->bulan)
                  ->whereYear('tanggal', $request->tahun);
        } elseif ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        }

        return $query->with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])->orderBy('tanggal', 'desc');
    }
}