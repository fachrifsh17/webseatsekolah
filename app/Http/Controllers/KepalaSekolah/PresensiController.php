<?php

namespace App\Http\Controllers\KepalaSekolah;

use App\Http\Controllers\Controller;
use App\Models\{Presensi, Siswa, Kelas, TahunAjaran};
use App\Http\Resources\PresensiResource;
use App\Exports\PresensiExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log};
use Illuminate\Support\Carbon;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class PresensiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
    }

    private function validateSemesterMonth(Request $request)
    {
        if ($request->filled('semester') && $request->filled('bulan')) {
            $semester = $request->semester;
            $bulan = (int) $request->bulan;

            if ($semester === 'Ganjil' && ($bulan < 7 || $bulan > 12)) {
                return "Untuk Semester Ganjil, pilih bulan antara 7 sampai 12 (Juli - Desember).";
            }

            if ($semester === 'Genap' && ($bulan < 1 || $bulan > 6)) {
                return "Untuk Semester Genap, pilih bulan antara 1 sampai 6 (Januari - Juni).";
            }
        }
        return null;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            if ($error = $this->validateSemesterMonth($request)) {
                return response()->json([
                    'success' => false, 
                    'message' => $error
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $query = Presensi::query();
            $query = $this->applyPresensiFilters($request, $query);

            $perPage = min((int) $request->get('per_page', 50), 100);
            $data = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => PresensiResource::collection($data),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'total'        => $data->total(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Kepsek Presensi Index Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal mengambil data.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        try {
            if (!$request->filled('kelas_id')) {
                return response()->json([
                    'success' => false, 
                    'message' => 'ID Kelas wajib dipilih.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($error = $this->validateSemesterMonth($request)) {
                return response()->json([
                    'success' => false, 
                    'message' => $error
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $kelas = Kelas::with(['waliKelas', 'tahunAjaran'])->findOrFail($request->kelas_id);
            
            $ta = $request->filled('tahun_ajaran_id') 
                ? TahunAjaran::find($request->tahun_ajaran_id) 
                : TahunAjaran::where('is_active', true)->first();

            if (!$ta) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Tahun Ajaran tidak ditemukan.'
                ], Response::HTTP_NOT_FOUND);
            }

            if ($request->filled('semester')) {
                $taMatched = TahunAjaran::where('nama', $ta->nama)
                    ->where('semester', $request->semester)
                    ->first();
                if ($taMatched) $ta = $taMatched;
            }

            $bulan = (int) $request->get('bulan', date('m'));
            $parts = explode('/', str_replace([' Ganjil', ' Genap'], '', $ta->nama));
            $tahunAwal = (int) $parts[0];
            $tahunAkhir = isset($parts[1]) ? (int) $parts[1] : $tahunAwal;

            $tahun = ($ta->semester === 'Ganjil') 
                ? (($bulan >= 1 && $bulan <= 6) ? $tahunAkhir : $tahunAwal)
                : (($bulan >= 7 && $bulan <= 12) ? $tahunAwal : $tahunAkhir);

            $profil = DB::table('profil_sekolah')->first() ?? DB::table('sekolah_setting')->first();
            $kontak = DB::table('data_kontak')->first();
            
            $labelWaktu = "Bulan-{$bulan}-Tahun-{$tahun}-Sem-{$ta->semester}";
            $taLabel = $ta->nama . " (" . $ta->semester . ")";

            return Excel::download(
                new PresensiExport(null, $kelas->nama_kelas, $labelWaktu, $profil, $kontak, $kelas, 'kepala_sekolah', $taLabel), 
                "Rekap_Presensi_{$kelas->nama_kelas}_{$labelWaktu}.xlsx"
            );
        } catch (Throwable $e) {
            Log::error('Kepsek Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal mengekspor data.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function applyPresensiFilters(Request $request, $query)
    {
        if (!$request->filled('kelas_id')) {
            return $query->whereRaw('1 = 0');
        }

        $ta = $request->filled('tahun_ajaran_id') 
            ? TahunAjaran::find($request->tahun_ajaran_id) 
            : TahunAjaran::where('is_active', true)->first();

        if ($ta) {
            if ($request->filled('semester')) {
                $taMatched = TahunAjaran::where('nama', $ta->nama)->where('semester', $request->semester)->first();
                if ($taMatched) $ta = $taMatched;
            }

            $query->where('tahun_ajaran_id', $ta->id);
            $bulan = (int) $request->get('bulan', date('m'));
            $parts = explode('/', str_replace([' Ganjil', ' Genap'], '', $ta->nama));
            $tahunAwal = (int) $parts[0];
            $tahunAkhir = isset($parts[1]) ? (int) $parts[1] : $tahunAwal;

            $tahun = ($ta->semester === 'Ganjil') 
                ? (($bulan >= 1 && $bulan <= 6) ? $tahunAkhir : $tahunAwal)
                : (($bulan >= 7 && $bulan <= 12) ? $tahunAwal : $tahunAkhir);

            if ($request->filled('tanggal')) {
                $query->whereDate('tanggal', $request->tanggal);
            } else {
                $query->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun);
            }
        }

        $query->whereHas('siswa', fn($q) => $q->where('is_active', true)->where('kelas_id', $request->kelas_id));

        if ($request->filled('search')) {
            $query->whereHas('siswa', fn($qs) => $qs->where('nama_lengkap', 'like', "%{$request->search}%"));
        }

        return $query->with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])->orderBy('tanggal', 'desc');
    }
}