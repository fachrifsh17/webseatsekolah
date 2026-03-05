<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{PresensiGuruMapel, GuruMapel, Siswa, TahunAjaran, Kelas, Semester};
use App\Http\Requests\{StorePresensiGuruMapelRequest, UpdatePresensiGuruMapelRequest};
use App\Http\Resources\PresensiGuruMapelResource;
use App\Exports\PresensiGuruMapelExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log};
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use Throwable;

class PresensiGuruMapelController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);
    }

    private function validateSemesterMonth(Request $request)
    {
        if ($request->filled('semester_id') && $request->filled('bulan')) {
            $semesterData = Semester::find($request->semester_id);
            if (!$semesterData) return "Semester tidak ditemukan.";

            $semester = $semesterData->nama;
            $bulan = (int) $request->bulan;

            if (strcasecmp($semester, 'Ganjil') == 0) {
                if ($bulan < 7 || $bulan > 12) {
                    return "Untuk Semester Ganjil, pilih bulan antara 7 sampai 12 (Juli - Desember).";
                }
            }

            if (strcasecmp($semester, 'Genap') == 0) {
                if ($bulan < 1 || $bulan > 6) {
                    return "Untuk Semester Genap, pilih bulan antara 1 sampai 6 (Januari - Juni).";
                }
            }
        }
        return null;
    }

    private function applyPresensiFilters(Request $request, $query)
    {
        $semesterId = $request->input('semester_id');
        
        if (!$semesterId) {
            $semesterAktif = Semester::where('is_active', true)->first();
            $semesterId = $semesterAktif ? $semesterAktif->id : null;
        }

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
            $semesterData = Semester::find($semesterId);

            if ($request->filled('tanggal')) {
                $query->whereDate('tanggal', $request->tanggal);
            } elseif ($request->filled('bulan')) {
                $bulan = (int) $request->bulan;
                $tahun = $semesterData->tahun;
                $query->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun);
            }
        }

        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', $request->kelas_id);
        }

        if ($request->filled('guru_staf_id')) {
            $query->where('guru_staf_id', $request->guru_staf_id);
        }

        if ($request->filled('mata_pelajaran_id')) {
            $query->where('mapel_id', $request->mata_pelajaran_id);
        }

        if ($request->filled('search')) {
            $keyword = $request->search;
            $query->where(function ($q) use ($keyword) {
                $q->whereHas('guru', function ($qg) use ($keyword) {
                    $qg->where('nama', 'like', "%{$keyword}%");
                })
                ->orWhereHas('mapel', function ($qm) use ($keyword) {
                    $qm->where('nama_mapel', 'like', "%{$keyword}%");
                })
                ->orWhereHas('presensiDetail.siswa', function ($qs) use ($keyword) {
                    $qs->where('nama_lengkap', 'like', "%{$keyword}%");
                })
                ->orWhere('materi', 'like', "%{$keyword}%");
            });
        }

        return $query;
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
        $this->authorize('viewAny', PresensiGuruMapel::class);

        if ($error = $this->validateSemesterMonth($request)) {
            return response()->json(['success' => false, 'message' => $error], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $semesterId = $request->input('semester_id');
        if (!$semesterId) {
            $semesterAktif = Semester::where('is_active', true)->first();
            $semesterId = $semesterAktif ? $semesterAktif->id : null;
        }

        $query = PresensiGuruMapel::with([
            'guru',
            'mapel',
            'kelas',
            'guruMapel.jamMulai',
            'guruMapel.jamSelesai',
            'presensiDetail'
        ])
        ->whereHas('mapel', fn($q) => $q->where('is_active', 1))
        ->where(function($q) use ($semesterId) {
            if ($semesterId) {
                $q->where('semester_id', $semesterId);
            }
        });

        $query = $this->applyPresensiFilters($request, $query);

        $perHalaman = min((int) $request->get('per_page', 20), 100);
        $paginasi = $query->latest('tanggal')->latest('id')->paginate($perHalaman);

        $data = $paginasi->getCollection()->map(function ($item) {
            $detail = $item->presensiDetail;
            return [
                'id' => $item->id,
                'tanggal' => Carbon::parse($item->tanggal)->toDateString(),
                'materi' => $item->materi,
                'guru_mapel' => [
                    'nama_guru' => $item->guru->nama ?? '-',
                    'mata_pelajaran' => $item->mapel->nama_mapel ?? '-',
                    'kelas' => $item->kelas->nama_kelas ?? '-',
                ],
                'total_siswa' => $detail->count(),
                'rekap' => [
                    'hadir' => $detail->filter(fn($d) => strcasecmp($d->status, 'Hadir') == 0)->count(),
                    'izin'  => $detail->filter(fn($d) => strcasecmp($d->status, 'Izin') == 0)->count(),
                    'sakit' => $detail->filter(fn($d) => strcasecmp($d->status, 'Sakit') == 0)->count(),
                    'alpa'  => $detail->filter(fn($d) => strcasecmp($d->status, 'Alpa') == 0)->count(),
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
                'from' => $paginasi->firstItem(),
                'to' => $paginasi->lastItem(),
                'path' => $paginasi->path(),
                'next_page_url' => $paginasi->nextPageUrl(),
                'prev_page_url' => $paginasi->previousPageUrl(),
                'links' => $paginasi->linkCollection()->toArray()
            ]
        ], Response::HTTP_OK);
    }

 public function store(StorePresensiGuruMapelRequest $request): JsonResponse
{
    $this->authorize('create', PresensiGuruMapel::class);

    $tanggalInput = $request->input('tanggal', Carbon::today()->toDateString());
    $cekLibur = $this->checkIsLibur($tanggalInput);

    if ($cekLibur['is_libur']) {
        return response()->json([
            'success' => false,
            'message' => "Gagal simpan. Tanggal tersebut adalah hari libur: {$cekLibur['keterangan']}."
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    $relasi = GuruMapel::where('is_active', 1)
        ->whereHas('mapel', fn($q) => $q->where('is_active', 1))
        ->whereHas('kelas', fn($q) => $q->where('is_active', 1))
        ->find($request->input('guru_mapel_id'));

    if (!$relasi) {
        return response()->json(['success' => false, 'message' => 'Data jadwal tidak ditemukan, tidak aktif, atau kelas/mapel sudah tidak aktif.'], Response::HTTP_NOT_FOUND);
    }

    $hariInput = Carbon::parse($tanggalInput)->locale('id')->dayName;

    if (strtolower($relasi->hari) !== strtolower($hariInput)) {
        return response()->json([
            'success' => false,
            'message' => "Gagal simpan. Jadwal adalah hari {$relasi->hari}, sedangkan tanggal yang dipilih adalah hari {$hariInput}."
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    $semesterId = $request->input('semester_id') ?? $relasi->semester_id;
    $inputPresensiRaw = collect($request->input('presensi', []));
    $siswaInputIds = $inputPresensiRaw->pluck('siswa_id')->toArray();

    $siswaAktifIds = Siswa::whereIn('id', $siswaInputIds)
        ->where('is_active', 1)
        ->pluck('id')
        ->toArray();

    $siswaIlegalAtauTidakAktif = array_diff($siswaInputIds, $siswaAktifIds);

    if (!empty($siswaIlegalAtauTidakAktif)) {
        return response()->json([
            'success' => false,
            'message' => 'Gagal simpan. Terdapat siswa yang tidak terdaftar atau sudah tidak aktif.',
            'invalid_ids' => array_values($siswaIlegalAtauTidakAktif)
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    $siswaTerdaftarDiKelasIds = $this->getSiswaDariRiwayat($relasi->kelas_id, $semesterId, $tanggalInput)
        ->whereIn('id', $siswaAktifIds)
        ->pluck('id')
        ->toArray();

    $siswaSalahKelas = array_diff($siswaAktifIds, $siswaTerdaftarDiKelasIds);
    
    if (!empty($siswaSalahKelas)) {
        return response()->json([
            'success' => false,
            'message' => 'Gagal simpan. Terdapat siswa yang tidak terdaftar di kelas ini pada semester tersebut.',
            'invalid_ids' => array_values($siswaSalahKelas)
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    try {
        $presensi = DB::transaction(function () use ($request, $relasi, $tanggalInput, $siswaTerdaftarDiKelasIds, $inputPresensiRaw, $semesterId) {
            $header = PresensiGuruMapel::updateOrCreate(
                [
                    'guru_mapel_id' => $relasi->id,
                    'tanggal' => $tanggalInput
                ],
                [
                    'semester_id' => $semesterId,
                    'guru_staf_id' => $relasi->guru_staf_id,
                    'kelas_id' => $relasi->kelas_id,
                    'mapel_id' => $relasi->mata_pelajaran_id,
                    'materi' => $request->input('materi'),
                ]
            );

            foreach ($siswaTerdaftarDiKelasIds as $siswaId) {
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
            'guru',
            'mapel',
            'kelas',
            'guruMapel.jamMulai',
            'guruMapel.jamSelesai',
        ])))
            ->additional(['success' => true, 'message' => 'Presensi berhasil disimpan.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    } catch (Throwable $e) {
        Log::error('Admin Simpan Jurnal Error: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Gagal simpan data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

    public function update(UpdatePresensiGuruMapelRequest $request, $id): JsonResponse
    {
        $presensi = PresensiGuruMapel::with('presensiDetail')->find($id);
        if (!$presensi) {
            return response()->json(['success' => false, 'message' => 'Data jurnal tidak ditemukan.'], Response::HTTP_NOT_FOUND);
        }
        
        $this->authorize('update', $presensi);

        $semesterAktif = Semester::where('is_active', true)->first();
        if (!$semesterAktif || $presensi->semester_id !== $semesterAktif->id) {
            return response()->json([
                'success' => false, 
                'message' => 'Gagal update. Data hanya bisa diubah pada semester aktif.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($request->has('presensi')) {
            $existingSiswaIds = $presensi->presensiDetail->pluck('siswa_id')->toArray();
            $inputSiswaIds = collect($request->input('presensi'))->pluck('siswa_id')->toArray();
            $siswaIlegal = array_diff($inputSiswaIds, $existingSiswaIds);

            if (!empty($siswaIlegal)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal update. Tidak diperbolehkan menyisipkan siswa baru ke dalam presensi yang sudah ada.',
                    'invalid_ids' => array_values($siswaIlegal)
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        try {
            $updated = DB::transaction(function () use ($request, $presensi) {
                if ($request->filled('materi')) {
                    $presensi->update(['materi' => $request->materi]);
                }

                if ($request->has('presensi')) {
                    foreach ($request->input('presensi') as $item) {
                        $presensi->presensiDetail()
                            ->where('siswa_id', $item['siswa_id'])
                            ->update([
                                'status' => $item['status'],
                                'catatan' => $item['catatan'] ?? null
                            ]);
                    }
                }
                return $presensi->refresh()->load([
                    'presensiDetail.siswa',
                    'guru',
                    'mapel',
                    'kelas',
                    'guruMapel.jamMulai',
                    'guruMapel.jamSelesai',
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Jurnal berhasil diperbarui.',
                'data' => new PresensiGuruMapelResource($updated)
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Admin Update Jurnal Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal update data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy($id): JsonResponse
    {
        $presensi = PresensiGuruMapel::find($id);
        if (!$presensi) {
            return response()->json(['success' => false, 'message' => 'Data jurnal tidak ditemukan.'], Response::HTTP_NOT_FOUND);
        }

        $this->authorize('delete', $presensi);

        try {
            DB::transaction(function () use ($presensi) {
                $presensi->presensiDetail()->delete();
                $presensi->delete();
            });

            return response()->json(['success' => true, 'message' => 'Jurnal berhasil dihapus.'], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menghapus data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id): JsonResponse
    {
        $presensi = PresensiGuruMapel::with([
            'presensiDetail.siswa',
            'guru',
            'mapel',
            'kelas',
            'guruMapel.jamMulai',
            'guruMapel.jamSelesai',
        ])->find($id);

        if (!$presensi) {
            return response()->json(['success' => false, 'message' => 'Data jurnal tidak ditemukan.'], Response::HTTP_NOT_FOUND);
        }

        $this->authorize('view', $presensi);

        return response()->json([
            'success' => true,
            'data' => new PresensiGuruMapelResource($presensi)
        ], Response::HTTP_OK);
    }

    public function listJadwalHariIni(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PresensiGuruMapel::class);

        try {
            $targetDate = $request->filled('tanggal')
                ? $request->tanggal
                : Carbon::now('Asia/Jakarta')->toDateString();

            $cekLibur = $this->checkIsLibur($targetDate);
            if ($cekLibur['is_libur']) {
                return response()->json([
                    'success' => false,
                    'message' => "Tidak dapat memuat jadwal. Tanggal tersebut adalah hari libur: {$cekLibur['keterangan']}.",
                    'error' => 'DATE_IS_HOLIDAY'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $dt = Carbon::parse($targetDate);
            $namaHari = $dt->locale('id')->dayName;

            $jadwalCollection = $this->getQueryJadwal($namaHari, $request);
            $sudahAbsen = $this->getPresensiExisting($targetDate);

            $dataMapped = $jadwalCollection->map(function ($j) use ($sudahAbsen) {
                $jurnal = $sudahAbsen->get($j->id);
                return [
                    'guru_mapel_id' => $j->id,
                    'jurnal_id' => $jurnal?->id,
                    'nama_guru' => $j->guru->nama ?? '-',
                    'mata_pelajaran' => $j->mapel->nama_mapel ?? '-',
                    'kelas' => $j->kelas->nama_kelas ?? '-',
                    'kelas_id' => $j->kelas_id,
                    'jam' => "Jam Ke " . ($j->jamMulai?->jam_ke ?? '-') . " - " . ($j->jamSelesai?->jam_ke ?? '-'),
                    'status' => $jurnal ? 'Sudah Absen' : 'Belum Absen',
                    'is_libur' => false,
                    'keterangan_libur' => null
                ];
            });

            if ($request->filled('status')) {
                $dataMapped = $dataMapped->filter(fn($item) => $item['status'] == $request->status)->values();
            }

            $perPage = min((int) $request->get('per_page', 20), 100);
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $pagedData = $dataMapped->slice(($currentPage - 1) * $perPage, $perPage)->values();

            $paginasi = new LengthAwarePaginator(
                $pagedData,
                $dataMapped->count(),
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return response()->json([
                'success' => true,
                'filter_info' => [
                    'tanggal' => $targetDate,
                    'hari' => $namaHari,
                    'is_hari_libur' => false,
                    'keterangan_hari' => 'Hari Efektif',
                    'total_jadwal' => $dataMapped->count()
                ],
                'data' => $paginasi->items(),
                'meta' => [
                    'current_page' => $paginasi->currentPage(),
                    'last_page' => $paginasi->lastPage(),
                    'per_page' => $paginasi->perPage(),
                    'total' => $paginasi->total(),
                    'from' => $paginasi->firstItem(),
                    'to' => $paginasi->lastItem(),
                    'path' => $paginasi->path(),
                    'next_page_url' => $paginasi->nextPageUrl(),
                    'prev_page_url' => $paginasi->previousPageUrl(),
                    'links' => $paginasi->linkCollection()->toArray()
                ]
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat jadwal.',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function getQueryJadwal($namaHari, Request $request)
    {
        $semesterId = $request->input('semester_id');
        
        if (!$semesterId) {
            $semesterAktif = Semester::where('is_active', true)->first();
            $semesterId = $semesterAktif ? $semesterAktif->id : null;
        }

        if (!$semesterId) {
            return collect();
        }

        $query = GuruMapel::with(['mapel', 'kelas', 'guru', 'jamMulai', 'jamSelesai'])
            ->where('is_active', 1)
            ->whereHas('mapel', fn($q) => $q->where('is_active', 1))
            ->whereHas('kelas', function ($q) {
                $q->where('is_active', 1);
            })
            ->where('semester_id', $semesterId)
            ->whereNotNull('jam_mulai_id')
            ->where('hari', $namaHari);

        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', $request->kelas_id);
        }

        if ($request->filled('guru_staf_id')) {
            $query->where('guru_staf_id', $request->guru_staf_id);
        }

        return $query->get();
    }

    private function getPresensiExisting($formattedDate)
    {
        return PresensiGuruMapel::whereDate('tanggal', $formattedDate)
            ->get(['id', 'guru_mapel_id'])
            ->keyBy('guru_mapel_id');
    }

    public function getSiswaByJadwal(Request $request, $guru_mapel_id): JsonResponse
    {
        $this->authorize('create', PresensiGuruMapel::class);

        try {
            $targetDate = $request->filled('tanggal')
                ? $request->tanggal
                : Carbon::today()->toDateString();

            $cekLibur = $this->checkIsLibur($targetDate);
            if ($cekLibur['is_libur']) {
                return $this->errorResponseLibur($cekLibur['keterangan']);
            }

            $jadwal = GuruMapel::with(['kelas', 'mapel', 'guru'])->where('is_active', 1)->find($guru_mapel_id);
            
            if (!$jadwal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data jadwal mengajar tidak ditemukan atau tidak aktif.'
                ], Response::HTTP_NOT_FOUND);
            }

            $semesterId = $request->input('semester_id') ?? $jadwal->semester_id;
            $presensiHeader = $this->getPresensiHeader($guru_mapel_id, $targetDate);

            $querySiswa = Siswa::query();

            if ($presensiHeader) {
                $querySiswa->whereHas('presensiSiswaDetail', function($q) use ($presensiHeader) {
                    $q->where('presensi_guru_mapel_id', $presensiHeader->id);
                });
            } else {
                $querySiswa->whereHas('riwayatKelas', function($q) use ($jadwal, $semesterId) {
                    $q->where('kelas_id', $jadwal->kelas_id)
                      ->where('semester_id', $semesterId);
                })->where('is_active', true);
            }

            $siswa = $querySiswa->orderBy('nama_lengkap', 'asc')->get();

            return response()->json([
                'success' => true,
                'info' => $this->formatInfoJadwal($jadwal, $presensiHeader, $targetDate),
                'data' => $this->mapSiswaDenganStatus($siswa, $presensiHeader)
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data.',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function errorResponseLibur($keterangan)
    {
        return response()->json([
            'success' => false,
            'message' => "Tidak dapat memuat data siswa. Tanggal tersebut adalah hari libur: {$keterangan}.",
            'error' => 'DATE_IS_HOLIDAY'
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function getPresensiHeader($guruMapelId, $tanggal)
    {
        return PresensiGuruMapel::where('guru_mapel_id', $guruMapelId)
            ->whereDate('tanggal', $tanggal)
            ->first();
    }

    private function getSiswaDariRiwayat($kelasId, $semesterId, $tanggal)
    {
        return Siswa::whereHas('riwayatKelas', function ($q) use ($kelasId, $semesterId) {
            $q->where('kelas_id', $kelasId)
              ->where('semester_id', $semesterId);
        })
        ->where('is_active', true)
        ->orderBy('nama_lengkap', 'asc')
        ->get();
    }

    private function formatInfoJadwal($jadwal, $header, $tanggal)
    {
        return [
            'jurnal_id' => $header->id ?? null,
            'guru_mapel_id' => $jadwal->id,
            'nama_guru' => $jadwal->guru->nama ?? '-',
            'mata_pelajaran' => $jadwal->mapel->nama_mapel ?? '-',
            'kelas' => $jadwal->kelas->nama_kelas ?? '-',
            'tanggal' => $tanggal,
            'sudah_isi_jurnal' => (bool)$header
        ];
    }

    private function mapSiswaDenganStatus($siswa, $header)
    {
        $detailExisting = $header ? $header->presensiDetail->keyBy('siswa_id') : collect();

        return $siswa->map(function ($s) use ($detailExisting) {
            $detail = $detailExisting->get($s->id);
            return [
                'siswa_id' => $s->id,
                'nama' => $s->nama_lengkap,
                'nisn' => $s->nisn,
                'status' => $detail ? $detail->status : null, 
                'catatan' => $detail ? $detail->catatan : null
            ];
        });
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', PresensiGuruMapel::class);

        if (!$request->filled(['semester_id', 'kelas_id', 'mata_pelajaran_id', 'guru_staf_id', 'bulan'])) {
            return response()->json([
                'success' => false,
                'message' => 'Semester, Kelas, Mata Pelajaran, Guru, dan Bulan wajib dipilih.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($error = $this->validateSemesterMonth($request)) {
            return response()->json(['success' => false, 'message' => $error], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $semesterData = Semester::find($request->semester_id);
        if (!$semesterData) {
            return response()->json(['success' => false, 'message' => 'Semester tidak ditemukan.'], Response::HTTP_NOT_FOUND);
        }
        $ta = TahunAjaran::find($semesterData->tahun_ajaran_id);

        $isSinkron = GuruMapel::where('semester_id', $semesterData->id)
            ->where('kelas_id', $request->kelas_id)
            ->where('mata_pelajaran_id', $request->mata_pelajaran_id)
            ->where('guru_staf_id', $request->guru_staf_id)
            ->where('is_active', 1)
            ->exists();

        if (!$isSinkron) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak sinkron: Kombinasi Guru, Mapel, dan Kelas tidak terdaftar atau tidak aktif pada Semester ini.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $guruTarget = DB::table('guru_staf')->where('id', $request->guru_staf_id)->first();
        if (!$guruTarget) {
            return response()->json(['success' => false, 'message' => 'Data guru tidak tersedia.'], Response::HTTP_NOT_FOUND);
        }

        $namaKelas = Kelas::where('id', $request->kelas_id)->value('nama_kelas') ?? 'Unknown';
        $namaMapel = DB::table('mata_pelajaran')->where('id', $request->mata_pelajaran_id)->value('nama_mapel') ?? 'Mapel';
        $namaGuru = $guruTarget->nama ?? 'Guru';

        $query = PresensiGuruMapel::query()->whereHas('mapel', fn($q) => $q->where('is_active', 1));
        $query = $this->applyPresensiFilters($request, $query);

        $bulan = (int) $request->bulan;
        $tahun = $semesterData->tahun;

        $namaKelasClean = str_replace([' ', '/'], '_', strtoupper($namaKelas));
        $namaMapelClean = str_replace([' ', '/'], '_', strtoupper($namaMapel));
        $namaGuruClean = str_replace([' ', '/'], '_', strtoupper($namaGuru));
        
        $taClean = str_replace(['/', ' '], '_', $ta->nama);
        $semesterNama = strtoupper($semesterData->nama);

        $filename = "REKAP_PRESENSI_{$namaKelasClean}_{$namaMapelClean}_{$namaGuruClean}_BULAN_{$bulan}_{$taClean}_{$semesterNama}.xlsx";

        return Excel::download(
            new PresensiGuruMapelExport(
                $query,
                "Bulan-{$bulan}-{$tahun}",
                DB::table('profil_sekolah')->first(),
                DB::table('data_kontak')->first(),
                (object)['nama' => $guruTarget->nama, 'nip' => $guruTarget->nip ?? '-'],
                $ta,
                true,
                $bulan,
                $tahun,
                $semesterData->id,
                'admin'
            ),
            $filename
        );
    }
}