<?php

namespace App\Http\Controllers\Walikelas;

use App\Http\Controllers\Controller;
use App\Models\{Presensi, Siswa, Kelas, TahunAjaran, GuruStaf};
use App\Http\Requests\{StorePresensiRequest, UpdatePresensiRequest};
use App\Http\Resources\PresensiResource;
use App\Exports\PresensiExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log, Auth};
use Throwable;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

class PresensiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Guru');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);
    }

    private function getIdentity(): array
    {
        $user = Auth::user();
        $guru = $user->guruStaf; 

        if (!$guru) return [null, null, null];

        $taAktif = TahunAjaran::where('is_active', true)->first();
        if (!$taAktif) return [$guru, null, null];

        $kelas = Kelas::where('wali_kelas_id', $guru->id)
            ->where('tahun_ajaran_id', $taAktif->id)
            ->where('is_active', 1)
            ->first();

        return [$guru, $kelas, $taAktif];
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', [Presensi::class]);
        [$guru, $kelas, $taAktif] = $this->getIdentity();

        if (!$guru || !$kelas) {
            return response()->json([
                'success' => false, 
                'message' => 'Profil Guru atau Kelas Perwalian pada Tahun Ajaran aktif tidak ditemukan.'
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            if ($request->filled('bulan') || $request->filled('semester') || $request->filled('tahun_ajaran_id')) {
                return $this->fetchHistory($request, $kelas->id);
            }

            return $this->fetchDaily($request, $guru, $kelas, $taAktif);
        } catch (Throwable $e) {
            Log::error('Walikelas Index Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        return response()->json([
            'success' => false,
            'message' => 'Detail presensi tidak ditemukan atau tidak tersedia.'
        ], Response::HTTP_NOT_FOUND);
    }

    public function store(StorePresensiRequest $request): JsonResponse
    {
        $this->authorize('create', [Presensi::class]);
        [$guru, $kelas, $taAktif] = $this->getIdentity();

        if (!$guru || !$kelas || !$taAktif) {
            return response()->json([
                'success' => false,
                'message' => 'Identitas Wali Kelas atau Kelas tidak valid.'
            ], Response::HTTP_FORBIDDEN);
        }

        $tanggalInput = date('Y-m-d');

        if ($this->isDayOff($tanggalInput, $taAktif->id)) {
            return response()->json([
                'success' => false, 
                'message' => 'Presensi ditolak. Hari ini adalah hari libur atau akhir pekan.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $waktuSekarang = date('H:i');
        $jamMulai = "06:30";
        $jamSelesai = "10:00";

        if ($waktuSekarang < $jamMulai || $waktuSekarang > $jamSelesai) {
            return response()->json([
                'success' => false,
                'message' => "Akses ditolak. Presensi hanya dapat diisi pada pukul {$jamMulai} sampai {$jamSelesai}. Saat ini pukul {$waktuSekarang}."
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $siswaIdSah = Siswa::where('kelas_id', $kelas->id)
                ->where('is_active', 1)
                ->pluck('id')
                ->toArray();

            $dataInput = $request->has('data_presensi') ? $request->input('data_presensi') : [$request->all()];

            $results = DB::transaction(function () use ($dataInput, $tanggalInput, $taAktif, $guru, $siswaIdSah) {
                $savedData = [];
                foreach ($dataInput as $item) {
                    if (!in_array($item['siswa_id'], $siswaIdSah)) {
                        throw new \Exception("Siswa dengan ID " . $item['siswa_id'] . " bukan bagian dari kelas perwalian Anda.");
                    }

                    $savedData[] = Presensi::updateOrCreate(
                        [
                            'siswa_id'        => $item['siswa_id'], 
                            'tanggal'         => $tanggalInput, 
                            'tahun_ajaran_id' => $taAktif->id
                        ],
                        [
                            'status'       => $item['status'],
                            'keterangan'   => $item['keterangan'] ?? 'Diinput oleh Wali Kelas',
                            'guru_staf_id' => $guru->id,
                        ]
                    );
                }
                return $savedData;
            });

            return response()->json([
                'success' => true, 
                'message' => count($results) . " data presensi berhasil disimpan (Waktu: {$waktuSekarang})."
            ], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false, 
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', [Presensi::class]);
        [$guru, $kelas, $taAktif] = $this->getIdentity();
        
        if (!$kelas) return response()->json(['success' => false, 'message' => 'Kelas tidak ditemukan.'], Response::HTTP_FORBIDDEN);

        try {
            if ($error = $this->validateSemesterMonth($request)) {
                return response()->json(['success' => false, 'message' => $error], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $ta = $request->filled('tahun_ajaran_id') ? TahunAjaran::find($request->tahun_ajaran_id) : $taAktif;
            $bulan = (int) $request->get('bulan', date('m'));
            $semesterTarget = $request->get('semester');

            if (!$semesterTarget) {
                $semesterTarget = ($bulan >= 7 && $bulan <= 12) ? 'Ganjil' : 'Genap';
            }

            if ($ta->semester !== $semesterTarget) {
                $taMatched = TahunAjaran::where('nama', $ta->nama)->where('semester', $semesterTarget)->first();
                if ($taMatched) $ta = $taMatched;
            }

            $pureYearName = trim(str_replace(['Ganjil', 'Genap'], '', $ta->nama));
            $parts = explode('/', $pureYearName);
            $tahunKalender = ($bulan >= 7 && $bulan <= 12) ? (int)$parts[0] : (int)($parts[1] ?? $parts[0]);

            $namaBulan = Carbon::create()->month($bulan)->translatedFormat('F');
            $labelWaktu = "Bulan-{$bulan}-Tahun-{$tahunKalender}";
            
            // --- MODIFIKASI NAMA FILE DISINI ---
            $taClean = str_replace(['/', ' '], '_', $ta->nama); 
            $fileName = "Rekap_Presensi_" . 
                        str_replace([' ', '/'], '_', $kelas->nama_kelas) . "_" . 
                        $namaBulan . "_" . 
                        $tahunKalender . "_TA_" . 
                        $taClean . "_" . 
                        $ta->semester . ".xlsx";
            // ------------------------------------

            $kelas->load('waliKelas');
            $profil = DB::table('profil_sekolah')->first();
            $kontak = DB::table('data_kontak')->first();
            
            return Excel::download(
                new PresensiExport(
                    $ta->id, 
                    $kelas->nama_kelas, 
                    $labelWaktu, 
                    $profil, 
                    $kontak, 
                    $kelas, 
                    'walikelas', 
                    $ta->nama . ' ' . $ta->semester
                ), 
                $fileName
            );
        } catch (Throwable $e) {
            Log::error('Export Error Walikelas: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal ekspor file.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function fetchHistory(Request $request, int $kelasId): JsonResponse
    {
        if ($error = $this->validateSemesterMonth($request)) {
            return response()->json(['success' => false, 'message' => $error], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $query = Presensi::query();
        $query = $this->applyPresensiFilters($request, $query, $kelasId);
        
        $perPage = min((int) $request->get('per_page', 50), 100);
        $data = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => PresensiResource::collection($data),
        ], Response::HTTP_OK);
    }

    private function fetchDaily(Request $request, $guru, $kelas, $taAktif): JsonResponse
    {
        $tanggal = $request->get('tanggal', date('Y-m-d'));
        $ta = $request->filled('tahun_ajaran_id') ? TahunAjaran::find($request->tahun_ajaran_id) : $taAktif;
        
        $isLibur = $this->isDayOff($tanggal, $ta->id);

        $waktuSekarang = date('H:i');
        $jamMulai = "06:30";
        $jamSelesai = "10:00";

        $siswa = Siswa::where('kelas_id', $kelas->id)
            ->where('is_active', 1)
            ->with(['presensi' => function($q) use ($tanggal, $ta) {
                $q->whereDate('tanggal', $tanggal)->where('tahun_ajaran_id', $ta->id);
            }])
            ->when($request->search, fn($q) => $q->where('nama_lengkap', 'like', "%{$request->search}%"))
            ->orderBy('nama_lengkap', 'asc')
            ->get();

        $collection = $siswa->map(fn($item) => [
            'siswa_id'    => $item->id,
            'nama'        => $item->nama_lengkap,
            'nisn'        => $item->nisn,
            'status'      => $item->presensi->first()?->status ?? null,
            'catatan'     => $item->presensi->first()?->keterangan ?? null,
            'presensi_id' => $item->presensi->first()?->id ?? null
        ]);

        return response()->json([
            'success' => true, 
            'info'    => [
                'guru'         => $guru->nama_lengkap ?? $guru->nama,
                'kelas'        => $kelas->nama_kelas,
                'tanggal'      => $tanggal,
                'hari'         => Carbon::parse($tanggal)->locale('id')->dayName,
                'tahun_ajaran' => $ta->nama . ' ' . $ta->semester,
                'is_libur'     => $isLibur,
                'is_editable'  => $tanggal === date('Y-m-d') && 
                                  !$isLibur && 
                                  ($waktuSekarang >= $jamMulai && $waktuSekarang <= $jamSelesai)
            ],
            'data'    => $collection
        ], Response::HTTP_OK);
    }

    private function applyPresensiFilters(Request $request, $query, $kelasId)
    {
        $ta = $request->filled('tahun_ajaran_id') 
            ? TahunAjaran::find($request->tahun_ajaran_id) 
            : TahunAjaran::where('is_active', true)->first();

        if ($ta) {
            $bulan = (int) $request->get('bulan', date('m'));
            $semesterTarget = $request->get('semester');
            if (!$semesterTarget && $request->filled('bulan')) {
                $semesterTarget = ($bulan >= 7 && $bulan <= 12) ? 'Ganjil' : 'Genap';
            }
            if ($semesterTarget && $ta->semester !== $semesterTarget) {
                $taMatched = TahunAjaran::where('nama', $ta->nama)->where('semester', $semesterTarget)->first();
                if ($taMatched) $ta = $taMatched;
            }
            $query->where('tahun_ajaran_id', $ta->id);
            $pureYearName = trim(str_replace(['Ganjil', 'Genap'], '', $ta->nama));
            $parts = explode('/', $pureYearName);
            $tahunKalender = ($bulan >= 7 && $bulan <= 12) ? $parts[0] : ($parts[1] ?? $parts[0]);
            if ($request->filled('tanggal')) {
                $query->whereDate('tanggal', $request->tanggal);
            } else {
                $query->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahunKalender);
            }
        }

        return $query->whereHas('siswa', fn($q) => $q->where('kelas_id', $kelasId)->where('is_active', 1))
            ->when($request->filled('search'), fn($q) => $q->whereHas('siswa', fn($qs) => $qs->where('nama_lengkap', 'like', "%{$request->search}%")))
            ->with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])
            ->orderBy('tanggal', 'desc');
    }

    private function validateSemesterMonth(Request $request): ?string
    {
        if ($request->filled('semester') && $request->filled('bulan')) {
            $bulan = (int) $request->bulan;
            if ($request->semester === 'Ganjil' && ($bulan < 7 || $bulan > 12)) return "Bulan {$bulan} tidak tersedia di Semester Ganjil.";
            if ($request->semester === 'Genap' && ($bulan < 1 || $bulan > 6)) return "Bulan {$bulan} tidak tersedia di Semester Genap.";
        }
        return null;
    }

    private function isDayOff($date, $tahunAjaranId = null): bool
    {
        $query = DB::table('kalender_akademik')->where('kategori', 'Libur')
            ->whereDate('tanggal_mulai', '<=', $date)
            ->whereDate('tanggal_selesai', '>=', $date);
        
        if ($tahunAjaranId) {
            $query->where('tahun_ajaran_id', $tahunAjaranId);
        }

        $libur = $query->exists();
        return $libur || date('N', strtotime($date)) >= 6;
    }
}