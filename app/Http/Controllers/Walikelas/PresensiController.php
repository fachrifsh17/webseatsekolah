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
use Illuminate\Support\Facades\{DB, Log, Auth, Schema};
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

        $taAktif = DB::table('tahun_ajaran')->where('is_active', true)->first();
        if (!$taAktif) return [$guru, null, null];

        $kelas = DB::table('kelas')
            ->where('wali_kelas_id', $guru->id)
            ->where('is_active', 1)
            ->select('id', 'nama_kelas', 'wali_kelas_id')
            ->first();

        return [$guru, $kelas, $taAktif];
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', [Presensi::class]);
        [$guru, $kelasAktif, $taAktif] = $this->getIdentity();

        if (!$guru) {
            return response()->json([
                'success' => false, 
                'message' => 'Profil Guru tidak ditemukan.'
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            if ($request->filled('bulan') || $request->filled('semester') || $request->filled('tahun_ajaran_id')) {
                $taId = $request->get('tahun_ajaran_id', $taAktif?->id);

                $kelasHistory = DB::table('kelas')
                    ->where('wali_kelas_id', $guru->id)
                    ->whereExists(function ($query) use ($taId) {
                        $query->select(DB::raw(1))
                            ->from('siswa_kelas')
                            ->whereColumn('siswa_kelas.kelas_id', 'kelas.id')
                            ->where('siswa_kelas.tahun_ajaran_id', $taId);
                    })->first();

                $targetKelasId = $kelasHistory ? $kelasHistory->id : ($kelasAktif?->id);

                if (!$targetKelasId) {
                    return response()->json([
                        'success' => false, 
                        'message' => 'Data riwayat kelas tidak ditemukan untuk periode ini.'
                    ], Response::HTTP_NOT_FOUND);
                }

                return $this->fetchHistory($request, $targetKelasId);
            }

            if (!$kelasAktif) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Kelas Perwalian aktif tidak ditemukan.'
                ], Response::HTTP_FORBIDDEN);
            }

            return $this->fetchDaily($request, $guru, $kelasAktif, $taAktif);
        } catch (Throwable $e) {
            Log::error('Walikelas Index Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        return response()->json([
            'success' => false,
            'message' => 'Detail presensi tidak ditemukan.'
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

        $now = Carbon::now();
        $tanggalInput = $now->toDateString();
        $jamSekarang = $now->format('H:i');

        if ($this->isDayOff($tanggalInput, $taAktif->id)) {
            return response()->json([
                'success' => false, 
                'message' => 'Presensi ditolak. Hari ini adalah hari libur atau akhir pekan.'
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($jamSekarang < '06:30' || $jamSekarang > '10:00') {
            return response()->json([
                'success' => false,
                'message' => "Presensi ditolak. Input hanya diperbolehkan pukul 06:30 s/d 10:00 WIB."
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $siswaIdWajib = DB::table('siswa_kelas')
                ->where('kelas_id', $kelas->id)
                ->where('tahun_ajaran_id', $taAktif->id)
                ->where('is_active', 1)
                ->pluck('siswa_id')
                ->toArray();

            $dataInput = $request->has('data_presensi') ? $request->input('data_presensi') : [$request->all()];
            $siswaIdInput = collect($dataInput)->pluck('siswa_id')->toArray();

            $siswaBelumInput = array_diff($siswaIdWajib, $siswaIdInput);

            if (count($siswaBelumInput) > 0) {
                $namaSiswaTerlewat = Siswa::whereIn('id', $siswaBelumInput)->pluck('nama_lengkap')->implode(', ');
                return response()->json([
                    'success' => false,
                    'message' => "Siswa berikut belum diisi: [{$namaSiswaTerlewat}]."
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $results = DB::transaction(function () use ($dataInput, $tanggalInput, $taAktif, $guru, $siswaIdWajib, $kelas) {
                $savedData = [];
                foreach ($dataInput as $item) {
                    if (!in_array($item['siswa_id'], $siswaIdWajib)) {
                        throw new \Exception("Siswa ID " . $item['siswa_id'] . " bukan bagian dari kelas Anda.");
                    }

                    $savedData[] = Presensi::updateOrCreate(
                        [
                            'siswa_id'        => $item['siswa_id'], 
                            'tanggal'         => $tanggalInput, 
                            'tahun_ajaran_id' => $taAktif->id
                        ],
                        [
                            'status'       => $item['status'],
                            'kelas_id'     => $kelas->id,
                            'keterangan'   => $item['keterangan'] ?? 'Diinput oleh Wali Kelas',
                            'guru_staf_id' => $guru->id,
                        ]
                    );
                }
                return $savedData;
            });

            return response()->json([
                'success' => true, 
                'message' => count($results) . " data presensi berhasil disimpan."
            ], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', [Presensi::class]);
        [$guru, $kelasAktif, $taAktif] = $this->getIdentity();
        
        try {
            $taId = $request->get('tahun_ajaran_id', $taAktif?->id);
            $ta = TahunAjaran::findOrFail($taId);

            $kelas = DB::table('kelas')
                ->where('wali_kelas_id', $guru->id)
                ->whereExists(function ($query) use ($taId) {
                    $query->select(DB::raw(1))
                        ->from('siswa_kelas')
                        ->whereColumn('siswa_kelas.kelas_id', 'kelas.id')
                        ->where('siswa_kelas.tahun_ajaran_id', $taId);
                })->first();

            if (!$kelas) {
                return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], Response::HTTP_FORBIDDEN);
            }

            if ($error = $this->validateSemesterMonth($request)) {
                return response()->json(['success' => false, 'message' => $error], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $bulan = (int) $request->get('bulan', date('m'));
            $semesterTarget = $request->get('semester') ?? (($bulan >= 7) ? 'Ganjil' : 'Genap');

            if ($ta->semester !== $semesterTarget) {
                $taMatched = TahunAjaran::where('nama', $ta->nama)->where('semester', $semesterTarget)->first();
                if ($taMatched) $ta = $taMatched;
            }

            $pureYearName = trim(str_replace(['Ganjil', 'Genap'], '', $ta->nama));
            $parts = explode('/', $pureYearName);
            $tahunKalender = ($bulan >= 7) ? (int)$parts[0] : (int)($parts[1] ?? $parts[0]);

            $labelWaktu = "Bulan-{$bulan}-Tahun-{$tahunKalender}";
            $namaKelasFile = str_replace([' ', '/'], '_', strtoupper($kelas->nama_kelas));
            $taClean = str_replace(['/', ' '], '_', strtoupper($ta->nama)); 
            $fileName = "REKAP_PRESENSI_{$namaKelasFile}_{$taClean}.xlsx";

            return Excel::download(
                new PresensiExport($ta->id, $kelas->nama_kelas, $labelWaktu, DB::table('profil_sekolah')->first(), DB::table('data_kontak')->first(), $kelas, 'walikelas', $ta->nama . ' ' . $ta->semester), 
                $fileName
            );
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal ekspor: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function fetchHistory(Request $request, string $kelasId): JsonResponse
    {
        if ($error = $this->validateSemesterMonth($request)) {
            return response()->json(['success' => false, 'message' => $error], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $query = Presensi::query();
        $query = $this->applyPresensiFilters($request, $query, $kelasId);
        $data = $query->paginate(min((int) $request->get('per_page', 50), 100));
        return response()->json(['success' => true, 'data' => PresensiResource::collection($data)], Response::HTTP_OK);
    }

    private function fetchDaily(Request $request, $guru, $kelas, $taAktif): JsonResponse
    {
        $now = Carbon::now();
        $tanggal = $request->get('tanggal', $now->toDateString());
        $jamSekarang = $now->format('H:i');
        $taId = $request->filled('tahun_ajaran_id') ? $request->tahun_ajaran_id : $taAktif->id;
        $ta = TahunAjaran::find($taId);
        
        $isLibur = $this->isDayOff($tanggal, $ta->id);
        $isTimeValid = ($jamSekarang >= '06:30' && $jamSekarang <= '10:00');
        $isEditable = ($tanggal === $now->toDateString() && !$isLibur && $isTimeValid);

        $siswa = Siswa::whereHas('riwayatKelas', function($q) use ($kelas, $ta) {
                $q->where('kelas_id', $kelas->id)
                  ->where('tahun_ajaran_id', $ta->id)
                  ->where('siswa_kelas.is_active', 1);
            })
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
                'guru'          => $guru->nama_lengkap ?? $guru->nama,
                'kelas'         => $kelas->nama_kelas,
                'tanggal'       => $tanggal,
                'hari'          => Carbon::parse($tanggal)->locale('id')->dayName,
                'tahun_ajaran' => $ta->nama . ' ' . $ta->semester,
                'is_libur'     => $isLibur,
                'is_editable'  => $isEditable,
                'current_time' => $jamSekarang
            ],
            'data'    => $collection
        ], Response::HTTP_OK);
    }

    private function applyPresensiFilters(Request $request, $query, string $kelasId)
    {
        $ta = $request->filled('tahun_ajaran_id') ? TahunAjaran::find($request->tahun_ajaran_id) : TahunAjaran::where('is_active', true)->first();
        if ($ta) {
            $bulan = (int) $request->get('bulan', date('m'));
            $semesterTarget = $request->get('semester') ?? (($bulan >= 7) ? 'Ganjil' : 'Genap');
            if ($ta->semester !== $semesterTarget) {
                $taMatched = TahunAjaran::where('nama', $ta->nama)->where('semester', $semesterTarget)->first();
                if ($taMatched) $ta = $taMatched;
            }
            $query->where('tahun_ajaran_id', $ta->id);
            $pureYearName = trim(str_replace(['Ganjil', 'Genap'], '', $ta->nama));
            $parts = explode('/', $pureYearName);
            $tahunKalender = ($bulan >= 7) ? $parts[0] : ($parts[1] ?? $parts[0]);
            
            if ($request->filled('tanggal')) {
                $query->whereDate('tanggal', $request->tanggal);
            } else {
                $query->whereMonth('tanggal', $bulan)->whereYear('tanggal', (int)$tahunKalender);
            }
        }

        $query->where('guru_staf_id', Auth::user()->guruStaf?->id);

        return $query->whereHas('siswa.riwayatKelas', function($q) use ($kelasId, $ta) {
                $q->where('kelas_id', $kelasId);
                if ($ta) $q->where('tahun_ajaran_id', $ta->id);
            })
            ->with(['siswa', 'guruStaf', 'tahunAjaran'])
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
        if ($tahunAjaranId && Schema::hasColumn('kalender_akademik', 'tahun_ajaran_id')) {
            $query->where('tahun_ajaran_id', $tahunAjaranId);
        }
        return $query->exists() || date('N', strtotime($date)) >= 6;
    }
}