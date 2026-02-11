<?php

namespace App\Http\Controllers\Walikelas;

use App\Http\Controllers\Controller;
use App\Models\{Presensi, Siswa, Kelas, TahunAjaran};
use App\Http\Resources\PresensiResource;
use App\Exports\PresensiExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Auth, DB, Log};
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PresensiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Guru');
        $this->middleware('log.aktivitas')->only(['store']);
    }

    public function siswaWali(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $kelas = Kelas::where('wali_kelas_id', $user->guru_staf_id)->where('is_active', true)->first();

            if (!$kelas) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Anda tidak memiliki kelas perwalian aktif.'
                ], Response::HTTP_FORBIDDEN);
            }

            $tanggal = $request->get('tanggal', date('Y-m-d'));
            $ta = TahunAjaran::where('is_active', true)->first();

            if (!$ta) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Tahun Ajaran aktif tidak ditemukan.'
                ], Response::HTTP_NOT_FOUND);
            }

            $summary = $this->getSummaryData($kelas->id, $tanggal, $ta->id);
            $collection = $this->getSiswaData($kelas->id, $tanggal, $ta->id, $request->search);

            return response()->json([
                'success' => true,
                'nama_kelas' => $kelas->nama_kelas,
                'summary' => $summary,
                'data' => $collection
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Walikelas List Siswa Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal memuat data siswa perwalian.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $tgl = date('Y-m-d');
        $jamMenit = now('Asia/Jakarta')->format('H:i');
        
        if ($this->isDayOff($tgl)) {
            return response()->json(['success' => false, 'message' => 'Input ditolak pada hari libur.'], Response::HTTP_FORBIDDEN);
        }

        if ($jamMenit < '06:30' || $jamMenit > '10:00') {
            return response()->json(['success' => false, 'message' => 'Input presensi hanya dilayani jam 06:30 - 10:00.'], Response::HTTP_FORBIDDEN);
        }

        $user = Auth::user();
        $kelas = Kelas::where('wali_kelas_id', $user->guru_staf_id)->where('is_active', true)->first();
        
        if (!$kelas) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak. Anda bukan wali kelas aktif.'], Response::HTTP_FORBIDDEN);
        }

        $ta = TahunAjaran::where('is_active', true)->firstOrFail();
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.siswa_id' => 'required|exists:siswa,id',
            'items.*.status' => 'required|in:Hadir,Sakit,Izin,Alpa',
            'items.*.keterangan' => 'nullable|string'
        ]);

        try {
            DB::transaction(function () use ($validated, $tgl, $user, $ta) {
                foreach ($validated['items'] as $item) {
                    Presensi::updateOrCreate(
                        ['siswa_id' => $item['siswa_id'], 'tanggal' => $tgl],
                        [
                            'tahun_ajaran_id' => $ta->id,
                            'status'          => $item['status'], 
                            'keterangan'      => $item['keterangan'] ?? 'Diinput oleh Wali Kelas: ' . $user->username, 
                            'guru_staf_id'    => $user->guru_staf_id
                        ]
                    );
                }
            });

            return response()->json(['success' => true, 'message' => 'Presensi berhasil disimpan.'], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Walikelas Store Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data presensi.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function index(Request $request): JsonResponse
    {
        if ($error = $this->validateSemesterMonth($request)) {
            return response()->json(['success' => false, 'message' => $error], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $user = Auth::user();
            $kelas = Kelas::where('wali_kelas_id', $user->guru_staf_id)->where('is_active', true)->first();

            if (!$kelas) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
            }

            $query = $this->applyPresensiFilters($request, Presensi::query(), $kelas);
            $perPage = min((int) $request->get('per_page', 20), 100);
            $data = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => "Riwayat presensi kelas {$kelas->nama_kelas} berhasil dimuat.",
                'data'    => PresensiResource::collection($data),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'total'        => $data->total(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memuat riwayat presensi.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        try {
            if ($error = $this->validateSemesterMonth($request)) {
                return response()->json(['success' => false, 'message' => $error], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $user = Auth::user();
            $kelas = Kelas::with(['waliKelas', 'tahunAjaran'])->where('wali_kelas_id', $user->guru_staf_id)->where('is_active', true)->first();

            if (!$kelas) return response()->json(['success' => false, 'message' => 'Kelas tidak ditemukan.'], Response::HTTP_FORBIDDEN);

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

            $labelWaktu = "Bulan-{$bulan}-Tahun-{$tahun}-Sem-{$ta->semester}";

            return Excel::download(
                new PresensiExport(null, $kelas->nama_kelas, $labelWaktu, DB::table('profil_sekolah')->first(), DB::table('data_kontak')->first(), $kelas, 'walikelas', $ta->nama), 
                "Rekap_Presensi_Walikelas_{$kelas->nama_kelas}_{$labelWaktu}.xlsx"
            );
        } catch (Throwable $e) {
            Log::error('Walikelas Export Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengekspor file.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function getSummaryData($kelasId, $tanggal, $taId): array
    {
        $summary = Presensi::whereHas('siswa', fn($q) => $q->where('kelas_id', $kelasId))
            ->whereDate('tanggal', $tanggal)
            ->where('tahun_ajaran_id', $taId)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'hadir' => $summary['Hadir'] ?? 0,
            'sakit' => $summary['Sakit'] ?? 0,
            'izin'  => $summary['Izin'] ?? 0,
            'alpa'  => $summary['Alpa'] ?? 0,
        ];
    }

    private function getSiswaData($kelasId, $tanggal, $taId, $search)
    {
        $siswaList = Siswa::where('kelas_id', $kelasId)
            ->where('is_active', true)
            ->with(['presensi' => function ($q) use ($tanggal, $taId) {
                $q->whereDate('tanggal', $tanggal)->where('tahun_ajaran_id', $taId);
            }])
            ->when($search, fn($q) => $q->where('nama_lengkap', 'like', "%{$search}%"))
            ->orderBy('nama_lengkap', 'asc')
            ->get();

        return $siswaList->map(fn($s) => [
            'siswa_id'      => $s->id,
            'nama_lengkap'  => $s->nama_lengkap,
            'nisn'          => $s->nisn,
            'jenis_kelamin' => $s->jenis_kelamin,
            'presensi'      => [
                'id'              => $s->presensi->first()?->id,
                'tanggal'         => $tanggal,
                'status'          => $s->presensi->first()?->status,
                'keterangan'      => $s->presensi->first()?->keterangan ?? '',
                'tahun_ajaran_id' => $taId
            ]
        ]);
    }

    private function applyPresensiFilters(Request $request, $query, $kelas)
    {
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
            $tahunAkhir = $parts[1] ?? $tahunAwal;
            $tahun = ($ta->semester === 'Ganjil') ? (($bulan >= 1 && $bulan <= 6) ? $tahunAkhir : $tahunAwal) : (($bulan >= 7 && $bulan <= 12) ? $tahunAwal : $tahunAkhir);

            if ($request->filled('tanggal')) {
                $query->whereDate('tanggal', $request->tanggal);
            } else {
                $query->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun);
            }
        }

        $query->whereHas('siswa', fn($q) => $q->where('kelas_id', $kelas->id)->where('is_active', true));

        if ($request->filled('search')) {
            $query->whereHas('siswa', fn($qs) => $qs->where('nama_lengkap', 'like', "%{$request->search}%"));
        }

        return $query->with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])->orderBy('tanggal', 'desc');
    }

    private function validateSemesterMonth(Request $request)
    {
        if ($request->filled('semester') && $request->filled('bulan')) {
            $bulan = (int) $request->bulan;
            if ($request->semester === 'Ganjil' && ($bulan < 7 || $bulan > 12)) return "Untuk Semester Ganjil, pilih bulan 7-12.";
            if ($request->semester === 'Genap' && ($bulan < 1 || $bulan > 6)) return "Untuk Semester Genap, pilih bulan 1-6.";
        }
        return null;
    }

    private function isDayOff($date): bool
    {
        $libur = DB::table('kalender_akademik')->where('kategori', 'Libur')
            ->whereDate('tanggal_mulai', '<=', $date)
            ->whereDate('tanggal_selesai', '>=', $date)->exists();
        return $libur || date('N', strtotime($date)) >= 6;
    }
}