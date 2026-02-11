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
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class PresensiController extends Controller
{
    use AuthorizesRequests;

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
        $this->authorize('viewAny', Presensi::class);

        if ($error = $this->validateSemesterMonth($request)) {
            return response()->json(['success' => false, 'message' => $error], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
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
            return response()->json(['success' => false, 'message' => 'Gagal mengambil data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function listKelas(Request $request): JsonResponse
    {
        // Menggunakan viewAny karena melihat daftar kelas untuk presensi adalah bagian dari monitoring
        $this->authorize('viewAny', Presensi::class);

        try {
            $taId = $request->get('tahun_ajaran_id') ?? TahunAjaran::where('is_active', true)->first()?->id;
            if (!$taId) return response()->json(['success' => false, 'message' => 'Tahun Ajaran tidak ditemukan.'], Response::HTTP_NOT_FOUND);

            $tanggal = $request->get('tanggal', date('Y-m-d'));

            $kelas = Kelas::where('tahun_ajaran_id', $taId)
                ->select('id', 'nama_kelas', 'tahun_ajaran_id')
                ->withCount(['siswa' => fn($q) => $q->where('is_active', true)])
                ->orderBy('nama_kelas', 'asc')
                ->get();

            $dataWithStatus = $kelas->map(function ($item) use ($tanggal, $taId) {
                $sudahAbsen = Presensi::where('tanggal', $tanggal)
                    ->where('tahun_ajaran_id', $taId)
                    ->whereHas('siswa', fn($q) => $q->where('kelas_id', $item->id))
                    ->exists();

                return [
                    'id' => $item->id,
                    'nama_kelas' => $item->nama_kelas,
                    'tahun_ajaran_id' => $item->tahun_ajaran_id,
                    'siswa_count' => $item->siswa_count,
                    'status_presensi' => $sudahAbsen ? 'Sudah Absen' : 'Belum Absen'
                ];
            });

            return response()->json(['success' => true, 'data' => $dataWithStatus], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memuat daftar kelas.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function listSiswaPresensi(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Presensi::class);

        if (!$request->filled('kelas_id')) {
            return response()->json(['success' => false, 'message' => 'ID Kelas wajib dipilih.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $tanggal = $request->get('tanggal', date('Y-m-d'));
            $ta = $request->filled('tahun_ajaran_id') 
                ? TahunAjaran::find($request->tahun_ajaran_id) 
                : TahunAjaran::where('is_active', true)->first();

            if (!$ta) return response()->json(['success' => false, 'message' => 'Tahun Ajaran tidak ditemukan.'], Response::HTTP_NOT_FOUND);

            $siswa = Siswa::where('kelas_id', $request->kelas_id)
                ->where('is_active', true)
                ->with(['kelas', 'presensi' => fn($q) => $q->whereDate('tanggal', $tanggal)->where('tahun_ajaran_id', $ta->id)])
                ->orderBy('nama_lengkap', 'asc')
                ->get();

            $collection = $siswa->map(function ($item) use ($tanggal, $ta) {
                $presensiExisting = $item->presensi->first();
                return [
                    'siswa_id'      => $item->id,
                    'nama_lengkap'  => $item->nama_lengkap,
                    'nisn'          => $item->nisn,
                    'jenis_kelamin' => $item->jenis_kelamin,
                    'presensi'      => [
                        'id'              => $presensiExisting?->id ?? null,
                        'tanggal'         => $tanggal,
                        'status'          => $presensiExisting?->status ?? null,
                        'keterangan'      => $presensiExisting?->keterangan ?? '',
                        'tahun_ajaran_id' => $ta->id
                    ]
                ];
            });

            return response()->json([
                'success' => true, 
                'data'    => $collection,
                'info'    => [
                    'tanggal' => $tanggal,
                    'kelas'   => Kelas::find($request->kelas_id)?->nama_kelas
                ]
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memuat daftar siswa.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', Presensi::class);

        try {
            if (!$request->filled('kelas_id')) {
                return response()->json(['success' => false, 'message' => 'ID Kelas wajib dipilih.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($error = $this->validateSemesterMonth($request)) {
                return response()->json(['success' => false, 'message' => $error], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $kelas = Kelas::with(['waliKelas', 'tahunAjaran'])->findOrFail($request->kelas_id);
            
            $ta = $request->filled('tahun_ajaran_id') 
                ? TahunAjaran::find($request->tahun_ajaran_id) 
                : TahunAjaran::where('is_active', true)->first();

            if (!$ta) return response()->json(['success' => false, 'message' => 'Tahun Ajaran tidak ditemukan.'], Response::HTTP_NOT_FOUND);

            $bulan = (int) $request->get('bulan', date('m'));
            $parts = explode('/', str_replace([' Ganjil', ' Genap'], '', $ta->nama));
            $tahunAwal = (int) $parts[0];
            $tahunAkhir = isset($parts[1]) ? (int) $parts[1] : $tahunAwal;

            $tahun = ($ta->semester === 'Ganjil') 
                ? (($bulan >= 1 && $bulan <= 6) ? $tahunAkhir : $tahunAwal)
                : (($bulan >= 7 && $bulan <= 12) ? $tahunAwal : $tahunAkhir);

            $profil = DB::table('profil_sekolah')->first();
            $kontak = DB::table('data_kontak')->first();
            
            $labelWaktu = "Bulan-{$bulan}-Tahun-{$tahun}-Sem-{$ta->semester}";
            $fileName = "Rekap_Presensi_Kepsek_{$kelas->nama_kelas}_{$labelWaktu}.xlsx";

            return Excel::download(
                new PresensiExport(null, $kelas->nama_kelas, $labelWaktu, $profil, $kontak, $kelas, 'kepala_sekolah', $ta->nama), 
                $fileName
            );
        } catch (Throwable $e) {
            Log::error('Kepsek Export Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengekspor data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
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