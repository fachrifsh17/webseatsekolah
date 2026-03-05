<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\{PresensiGuruMapel, GuruMapel, Siswa, TahunAjaran, Semester};
use App\Http\Requests\StorePresensiGuruMapelRequest;
use App\Http\Resources\PresensiGuruMapelResource;
use App\Exports\PresensiGuruMapelExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log};
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;
use Throwable;

class PresensiGuruMapelController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Guru');
        $this->middleware('log.aktivitas')->only(['store']);
    }

    private function getGuruId(Request $request)
    {
        return $request->user()->guruStaf?->id ?? null;
    }

    private function validateSemesterMonth(Request $request): ?string
    {
        if (!$request->filled('semester_id') || !$request->filled('bulan')) {
            return null;
        }

        $semesterData = Semester::where('id', $request->semester_id)->first();
        if (!$semesterData) {
            return "Semester tidak ditemukan.";
        }

        $bulan = (int) $request->bulan;
        $isGanjil = strcasecmp($semesterData->nama, 'Ganjil') === 0;
        $isGenap = strcasecmp($semesterData->nama, 'Genap') === 0;

        if ($isGanjil && ($bulan < 7 || $bulan > 12)) {
            return "Untuk Semester Ganjil, pilih bulan antara 7 sampai 12 (Juli - Desember).";
        }

        if ($isGenap && ($bulan < 1 || $bulan > 6)) {
            return "Untuk Semester Genap, pilih bulan antara 1 sampai 6 (Januari - Juni).";
        }

        return null;
    }

    private function applyPresensiFilters(Request $request, $query, $guruId)
    {
        $semesterId = $request->input('semester_id') ?? Semester::where('is_active', true)->first()?->id;

        $query->where('guru_staf_id', $guruId);

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        } elseif ($request->filled('bulan')) {
            $semesterData = Semester::find($semesterId);
            $bulan = (int) $request->bulan;
            $tahun = $semesterData->tahun ?? Carbon::now()->year;
            $query->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun);
        }

        $query->when($request->filled('kelas_id'), function ($q) use ($request) {
            $q->where('kelas_id', $request->kelas_id);
        });

        $query->when($request->filled('mata_pelajaran_id'), function ($q) use ($request) {
            $q->where('mapel_id', $request->mata_pelajaran_id);
        });

        if ($request->filled('search')) {
            $keyword = $request->search;
            $query->where(function ($q) use ($keyword) {
                $q->whereHas('guruMapel.mapel', fn($qm) => $qm->where('nama_mapel', 'like', "%{$keyword}%"))
                  ->orWhereHas('presensiDetail.siswa', fn($qs) => $qs->where('nama_lengkap', 'like', "%{$keyword}%"))
                  ->orWhere('materi', 'like', "%{$keyword}%");
            });
        }

        return $query;
    }

    private function determineYear(?TahunAjaran $ta, ?Semester $semesterData, int $bulan): int
    {
        if ($semesterData && $semesterData->tahun) {
            return (int) $semesterData->tahun;
        }

        if (!$ta) return Carbon::now()->year;
        $namaTA = str_replace([' Ganjil', ' Genap'], '', $ta->nama);
        $parts = explode('/', $namaTA);
        $tahunAwal = (int) $parts[0];
        $tahunAkhir = isset($parts[1]) ? (int) $parts[1] : $tahunAwal;

        if ($semesterData && strcasecmp($semesterData->nama, 'Ganjil') === 0) {
            return ($bulan >= 1 && $bulan <= 6) ? $tahunAkhir : $tahunAwal;
        }
        return ($bulan >= 7 && $bulan <= 12) ? $tahunAwal : $tahunAkhir;
    }

    private function checkIsLibur($tanggal)
    {
        $dt = Carbon::parse($tanggal);
        $isWeekend = $dt->isWeekend();
        $semesterAktif = Semester::where('is_active', true)->first();
        $liburKalender = null;

        if ($semesterAktif) {
            $liburKalender = DB::table('kalender_akademik')
                ->where('semester_id', $semesterAktif->id)
                ->whereDate('tanggal_mulai', '<=', $tanggal)
                ->whereDate('tanggal_selesai', '>=', $tanggal)
                ->first();
        }

        if ($isWeekend || $liburKalender) {
            return [
                'is_libur' => true,
                'keterangan' => $liburKalender ? $liburKalender->kegiatan : "Hari " . $dt->locale('id')->dayName . " (Libur Akhir Pekan)"
            ];
        }
        return ['is_libur' => false];
    }

    public function index(Request $request): JsonResponse
    {
        $guruId = $this->getGuruId($request);
        if (!$guruId) {
            return response()->json(['success' => false, 'message' => 'Guru tidak ditemukan.'], Response::HTTP_FORBIDDEN);
        }

        $this->authorize('viewAny', PresensiGuruMapel::class);

        if ($error = $this->validateSemesterMonth($request)) {
            return response()->json(['success' => false, 'message' => $error], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $query = PresensiGuruMapel::with([
            'guruMapel.guru',
            'guruMapel.mapel',
            'guruMapel.jamMulai',
            'guruMapel.jamSelesai',
            'guruMapel.kelas',
            'presensiDetail'
        ]);

        $query = $this->applyPresensiFilters($request, $query, $guruId);

        $perHalaman = min((int) $request->get('per_page', 20), 100);
        $paginasi = $query->latest('tanggal')->latest('id')->paginate($perHalaman);

        $data = $paginasi->getCollection()->map(function ($item) {
            $detail = $item->presensiDetail;
            return [
                'id' => $item->id,
                'tanggal' => Carbon::parse($item->tanggal)->format('Y-m-d'),
                'materi' => $item->materi,
                'guru_mapel' => [
                    'nama_guru' => $item->guruMapel->guru->nama ?? '-',
                    'mata_pelajaran' => $item->guruMapel->mapel->nama_mapel ?? '-',
                    'kelas' => $item->guruMapel->kelas->nama_kelas ?? '-',
                ],
                'total_siswa' => $detail->count(),
                'rekap' => [
                    'hadir' => $detail->filter(fn($d) => strtolower($d->status) === 'hadir')->count(),
                    'izin' => $detail->filter(fn($d) => strtolower($d->status) === 'izin')->count(),
                    'sakit' => $detail->filter(fn($d) => strtolower($d->status) === 'sakit')->count(),
                    'alpa' => $detail->filter(fn($d) => strtolower($d->status) === 'alpa')->count(),
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginasi->currentPage(),
                'last_page' => $paginasi->lastPage(),
                'per_page' => $paginasi->perPage(),
                'total' => $paginasi->total(),
                'has_more_pages' => $paginasi->hasMorePages(),
                'next_page_url' => $paginasi->nextPageUrl(),
                'prev_page_url' => $paginasi->previousPageUrl(),
            ]
        ], Response::HTTP_OK);
    }

    public function store(StorePresensiGuruMapelRequest $request): JsonResponse
    {
        $guruId = $this->getGuruId($request);
        if (!$guruId) {
            return response()->json(['success' => false, 'message' => 'Guru tidak ditemukan.'], Response::HTTP_FORBIDDEN);
        }

        $this->authorize('create', PresensiGuruMapel::class);

        $now = Carbon::now('Asia/Jakarta');
        $tanggalInput = $now->toDateString();
        $currentTime = $now->format('H:i:s');
        
        $cekLibur = $this->checkIsLibur($tanggalInput);
        if ($cekLibur['is_libur']) {
            return response()->json([
                'success' => false,
                'message' => "Gagal simpan. Tanggal tersebut adalah hari libur: {$cekLibur['keterangan']}."
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $relasi = GuruMapel::with(['jamMulai', 'jamSelesai'])
            ->where('is_active', 1)
            ->where('guru_staf_id', $guruId)
            ->whereHas('mapel', fn($q) => $q->where('is_active', 1))
            ->whereHas('kelas', fn($q) => $q->where('is_active', 1))
            ->find($request->input('guru_mapel_id'));

        if (!$relasi) {
            return response()->json(['success' => false, 'message' => 'Data jadwal tidak ditemukan, tidak aktif, atau bukan milik Anda.'], Response::HTTP_NOT_FOUND);
        }

        $hariInput = $now->locale('id')->dayName;
        if (strcasecmp($relasi->hari, $hariInput) !== 0) {
            return response()->json([
                'success' => false,
                'message' => "Gagal simpan. Jadwal adalah hari {$relasi->hari}, sedangkan hari ini adalah hari {$hariInput}."
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($relasi->jamMulai && $relasi->jamSelesai) {
            $startTimeStr = Carbon::parse($relasi->jamMulai->waktu_mulai)->format('H:i:s');
            $endTimeStr = Carbon::parse($relasi->jamSelesai->waktu_selesai)->format('H:i:s');

            if ($currentTime < $startTimeStr) {
                return response()->json([
                    'success' => false,
                    'message' => "Gagal simpan. Sesi mata pelajaran belum dimulai (Jadwal: {$startTimeStr} - {$endTimeStr})."
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($currentTime > $endTimeStr) {
                return response()->json([
                    'success' => false,
                    'message' => "Gagal simpan. Sesi mata pelajaran sudah berakhir (Jadwal: {$startTimeStr} - {$endTimeStr})."
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $semesterId = $request->input('semester_id') ?? $relasi->semester_id ?? Semester::where('is_active', true)->first()?->id;
        
        $inputPresensiRaw = collect($request->input('presensi', []));
        $siswaInputIds = $inputPresensiRaw->pluck('siswa_id')->toArray();

        $siswaAktifIds = Siswa::whereIn('id', $siswaInputIds)
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();

        $siswaTidakAktif = array_diff($siswaInputIds, $siswaAktifIds);
        if (!empty($siswaTidakAktif)) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal simpan. Terdapat siswa yang sudah tidak aktif.',
                'error_code' => 'INACTIVE_STUDENT'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $siswaSahIds = Siswa::whereIn('id', $siswaInputIds)
            ->whereHas('riwayatKelas', function ($q) use ($relasi, $semesterId) {
                $q->where('kelas_id', $relasi->kelas_id)
                  ->where('semester_id', $semesterId);
            })
            ->pluck('id')
            ->toArray();

        $siswaLuarKelas = array_diff($siswaInputIds, $siswaSahIds);
        if (!empty($siswaLuarKelas)) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal simpan. Terdapat siswa yang tidak terdaftar di kelas ini pada semester ini.',
                'error_code' => 'INVALID_STUDENT_CLASS'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $totalSiswaSeharusnya = Siswa::where('is_active', true)
            ->whereHas('riwayatKelas', function ($q) use ($relasi, $semesterId) {
                $q->where('kelas_id', $relasi->kelas_id)
                  ->where('semester_id', $semesterId);
            })->count();

        if (count(array_unique($siswaInputIds)) !== $totalSiswaSeharusnya) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal simpan. Semua siswa dalam kelas ini wajib diisi presensinya.',
                'error_code' => 'INCOMPLETE_PRESENCE'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $presensi = DB::transaction(function () use ($request, $relasi, $tanggalInput, $siswaSahIds, $inputPresensiRaw, $semesterId) {
                $header = PresensiGuruMapel::updateOrCreate(
                    [
                        'guru_mapel_id' => $relasi->id,
                        'tanggal' => $tanggalInput
                    ],
                    [
                        'semester_id' => $semesterId,
                        'guru_staf_id' => $relasi->guru_staf_id,
                        'kelas_id'      => $relasi->kelas_id,
                        'mapel_id'      => $relasi->mata_pelajaran_id,
                        'materi'        => $request->input('materi')
                    ]
                );

                foreach ($siswaSahIds as $siswaId) {
                    $dataSiswa = $inputPresensiRaw->firstWhere('siswa_id', $siswaId);
                    $header->presensiDetail()->updateOrCreate(
                        ['siswa_id' => $siswaId],
                        [
                            'status' => $dataSiswa['status'] ?? 'hadir',
                            'catatan' => $dataSiswa['catatan'] ?? null
                        ]
                    );
                }
                return $header;
            });

            return (new PresensiGuruMapelResource($presensi->load([
                'presensiDetail.siswa',
                'guruMapel.guru',
                'guruMapel.mapel',
                'guruMapel.jamMulai',
                'guruMapel.jamSelesai',
            ])))
            ->additional(['success' => true, 'message' => 'Jurnal & Presensi berhasil disimpan.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Guru Simpan Jurnal Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal simpan data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        $guruId = $this->getGuruId($request);
        if (!$guruId) {
            return response()->json(['success' => false, 'message' => 'Guru tidak ditemukan.'], Response::HTTP_FORBIDDEN);
        }

        $presensi = PresensiGuruMapel::with([
            'presensiDetail.siswa',
            'guruMapel.guru',
            'guruMapel.mapel',
            'guruMapel.jamMulai',
            'guruMapel.jamSelesai',
        ])
        ->where('guru_staf_id', $guruId)
        ->find($id);

        if (!$presensi) {
            return response()->json(['success' => false, 'message' => 'Data jurnal tidak ditemukan atau bukan milik Anda.'], Response::HTTP_NOT_FOUND);
        }

        $this->authorize('view', $presensi);

        return response()->json([
            'success' => true,
            'data' => new PresensiGuruMapelResource($presensi)
        ], Response::HTTP_OK);
    }

    public function listJadwalHariIni(Request $request): JsonResponse
    {
        $guruId = $this->getGuruId($request);
        if (!$guruId) {
            return response()->json(['success' => false, 'message' => 'Guru tidak ditemukan.'], Response::HTTP_FORBIDDEN);
        }
        
        $this->authorize('viewAny', PresensiGuruMapel::class);

        try {
            $targetDate = $request->input('tanggal') ?? Carbon::now('Asia/Jakarta')->toDateString();
            $dt = Carbon::parse($targetDate);
            $namaHari = $dt->locale('id')->dayName;

            $cekLibur = $this->checkIsLibur($targetDate);
            if ($cekLibur['is_libur']) {
                return response()->json([
                    'success' => true,
                    'filter_info' => [
                        'tanggal' => $targetDate,
                        'hari' => $namaHari,
                        'is_hari_libur' => true,
                        'keterangan_hari' => $cekLibur['keterangan'],
                        'total_jadwal' => 0
                    ],
                    'data' => [],
                    'message' => "Hari libur: " . $cekLibur['keterangan']
                ], Response::HTTP_OK);
            }

            $jadwalCollection = $this->getQueryJadwal($namaHari, $request, $guruId);
            $sudahAbsen = $this->getPresensiExisting($targetDate, $guruId);

            $dataMapped = $jadwalCollection->map(function ($j) use ($sudahAbsen) {
                $jurnal = $sudahAbsen->get($j->id);
                return [
                    'guru_mapel_id' => $j->id,
                    'jurnal_id' => $jurnal?->id,
                    'nama_guru' => $j->guru?->nama ?? '-',
                    'mata_pelajaran' => $j->mapel?->nama_mapel ?? '-',
                    'kelas' => $j->kelas?->nama_kelas ?? '-',
                    'kelas_id' => $j->kelas_id,
                    'jam' => "Jam Ke " . ($j->jamMulai?->jam_ke ?? '-') . " - " . ($j->jamSelesai?->jam_ke ?? '-'),
                    'status' => $jurnal ? 'Sudah Absen' : 'Belum Absen',
                ];
            });

            if ($request->filled('status')) {
                $dataMapped = $dataMapped->filter(fn($item) => $item['status'] == $request->status)->values();
            }

            return response()->json([
                'success' => true,
                'filter_info' => [
                    'tanggal' => $targetDate,
                    'hari' => $namaHari,
                    'is_hari_libur' => false,
                    'total_jadwal' => $dataMapped->count()
                ],
                'data' => $dataMapped
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memuat jadwal.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function getQueryJadwal($namaHari, Request $request, $guruId)
    {
        $semesterId = $request->input('semester_id') ?? Semester::where('is_active', true)->first()?->id;
        if (!$semesterId) return collect();

        return GuruMapel::with(['mapel', 'kelas', 'guru', 'jamMulai', 'jamSelesai'])
            ->where('is_active', 1)
            ->where('guru_staf_id', $guruId)
            ->whereHas('mapel', fn($q) => $q->where('is_active', 1))
            ->whereHas('kelas', fn($q) => $q->where('is_active', 1))
            ->where('semester_id', $semesterId)
            ->where('hari', $namaHari)
            ->when($request->filled('kelas_id'), fn($q) => $q->where('kelas_id', $request->kelas_id))
            ->orderBy(DB::table('jam_sekolah')->select('jam_ke')->whereColumn('id', 'guru_mapel.jam_mulai_id'))
            ->get();
    }

    private function getPresensiExisting($formattedDate, $guruId)
    {
        return PresensiGuruMapel::whereDate('tanggal', $formattedDate)
            ->where('guru_staf_id', $guruId)
            ->get(['id', 'guru_mapel_id'])
            ->keyBy('guru_mapel_id');
    }

    public function getSiswaByJadwal(Request $request, $guru_mapel_id): JsonResponse
    {
        $guruId = $this->getGuruId($request);
        if (!$guruId) {
            return response()->json(['success' => false, 'message' => 'Guru tidak ditemukan.'], Response::HTTP_FORBIDDEN);
        }

        $this->authorize('create', PresensiGuruMapel::class);

        try {
            $targetDate = $request->input('tanggal') ?? Carbon::today()->toDateString();
            
            $cekLibur = $this->checkIsLibur($targetDate);
            if ($cekLibur['is_libur']) {
                return response()->json([
                    'success' => false,
                    'message' => "Tidak dapat memuat data siswa. Tanggal tersebut adalah hari libur: {$cekLibur['keterangan']}.",
                    'error' => 'DATE_IS_HOLIDAY'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $jadwal = GuruMapel::with(['kelas', 'mapel', 'guru'])
                ->where('is_active', 1)
                ->where('guru_staf_id', $guruId)
                ->whereHas('mapel', fn($q) => $q->where('is_active', 1))
                ->whereHas('kelas', fn($q) => $q->where('is_active', 1))
                ->find($guru_mapel_id);
            
            if (!$jadwal) {
                return response()->json(['success' => false, 'message' => 'Data jadwal tidak ditemukan atau bukan milik Anda.'], Response::HTTP_NOT_FOUND);
            }

            $semesterId = $request->input('semester_id') ?? $jadwal->semester_id ?? Semester::where('is_active', true)->first()?->id;
            
            $presensiHeader = PresensiGuruMapel::with(['presensiDetail'])
                ->where('guru_mapel_id', $guru_mapel_id)
                ->whereDate('tanggal', $targetDate)
                ->first();

            $querySiswa = Siswa::query();
            
            if ($presensiHeader) {
                $querySiswa->whereHas('presensiSiswaDetail', function($q) use ($presensiHeader) {
                    $q->where('presensi_guru_mapel_id', $presensiHeader->id);
                });
            } else {
                $querySiswa->whereHas('riwayatKelas', function($q) use ($jadwal, $semesterId) {
                    $q->where('kelas_id', $jadwal->kelas_id)
                      ->where('semester_id', $semesterId);
                });

                if ($targetDate === Carbon::today()->toDateString()) {
                    $querySiswa->where('is_active', true);
                }
            }

            $siswa = $querySiswa->orderBy('nama_lengkap', 'asc')->get();
            $detailExisting = $presensiHeader ? $presensiHeader->presensiDetail->keyBy('siswa_id') : collect();

            $dataSiswa = $siswa->map(function ($s) use ($detailExisting) {
                $detail = $detailExisting->get($s->id);
                return [
                    'siswa_id' => $s->id,
                    'nama' => $s->nama_lengkap,
                    'nisn' => $s->nisn,
                    'status' => $detail ? $detail->status : null, 
                    'catatan' => $detail ? $detail->catatan : null
                ];
            });

            return response()->json([
                'success' => true,
                'info' => [
                    'jurnal_id' => $presensiHeader->id ?? null,
                    'guru_mapel_id' => $jadwal->id,
                    'nama_guru' => $jadwal->guru->nama ?? '-',
                    'mata_pelajaran' => $jadwal->mapel->nama_mapel ?? '-',
                    'kelas' => $jadwal->kelas->nama_kelas ?? '-',
                    'tanggal' => $targetDate,
                    'sudah_isi_jurnal' => (bool)$presensiHeader,
                    'materi' => $presensiHeader->materi ?? null
                ],
                'data' => $dataSiswa
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Gagal memuat data.',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        $guruId = $this->getGuruId($request);
        if (!$guruId) {
            return response()->json(['success' => false, 'message' => 'Guru tidak ditemukan.'], Response::HTTP_FORBIDDEN);
        }

        $this->authorize('viewAny', PresensiGuruMapel::class);

        $requiredFields = ['semester_id', 'kelas_id', 'mata_pelajaran_id', 'bulan'];
        if (!$request->filled($requiredFields)) {
            return response()->json(['success' => false, 'message' => 'Semester, Kelas, Mata Pelajaran, dan Bulan wajib dipilih.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($error = $this->validateSemesterMonth($request)) {
            return response()->json(['success' => false, 'message' => $error], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $semesterId = $request->input('semester_id') ?? Semester::where('is_active', true)->first()?->id;
        $semesterData = Semester::where('id', $semesterId)->first();
        if (!$semesterData) {
            return response()->json(['success' => false, 'message' => 'Semester tidak ditemukan.'], Response::HTTP_NOT_FOUND);
        }
        
        $ta = TahunAjaran::find($semesterData->tahun_ajaran_id);

        $jadwal = GuruMapel::with(['kelas', 'mapel'])->where([
                'semester_id' => $semesterData->id,
                'kelas_id' => $request->kelas_id,
                'mata_pelajaran_id' => $request->mata_pelajaran_id,
                'guru_staf_id' => $guruId,
                'is_active' => 1
            ])->first();

        if (!$jadwal) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan atau bukan milik Anda.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $query = PresensiGuruMapel::query();
        $query = $this->applyPresensiFilters($request, $query, $guruId);

        $bulan = (int) $request->bulan;
        $tahun = (int) ($semesterData->tahun ?? $this->determineYear($ta, $semesterData, $bulan));

        $namaKelas = strtoupper(str_replace(' ', '_', $jadwal->kelas->nama_kelas ?? 'KELAS'));
        $namaMapel = strtoupper(str_replace(' ', '_', $jadwal->mapel->nama_mapel ?? 'MAPEL'));
        $namaGuru = strtoupper(str_replace(' ', '_', $request->user()->name ?? 'GURU'));
        $tahunAjaran = strtoupper(str_replace(['/', ' '], ['_', '_'], $ta->nama ?? 'TA'));
        $semesterNama = strtoupper($semesterData->nama ?? 'SEMESTER');

        $fileName = "REKAP_PRESENSI_{$namaKelas}_{$namaMapel}_{$namaGuru}_BULAN_{$bulan}_{$tahunAjaran}_{$semesterNama}.xlsx";

        return Excel::download(
            new PresensiGuruMapelExport(
                $query,
                "Bulan-{$bulan}-{$tahun}",
                DB::table('profil_sekolah')->first(),
                DB::table('data_kontak')->first(),
                (object)['nama' => $request->user()->name, 'nip' => $request->user()->guruStaf?->nip ?? '-'],
                (object)['nama' => $ta->nama ?? '-'],
                true,
                $bulan,
                $tahun,
                $semesterData->id,
                'guru'
            ),
            $fileName
        );
    }
}