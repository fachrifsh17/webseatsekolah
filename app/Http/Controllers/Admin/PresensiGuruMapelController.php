<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{PresensiGuruMapel, GuruMapel, Siswa, TahunAjaran, Kelas};
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

    private function applyPresensiFilters(Request $request, $query)
    {
        $ta = $request->filled('tahun_ajaran_id') 
            ? TahunAjaran::find($request->tahun_ajaran_id) 
            : TahunAjaran::where('is_active', true)->first();

        if ($ta) {
            $query->where('tahun_ajaran_id', $ta->id);
            
            if ($request->filled('tanggal')) {
                $query->whereDate('tanggal', $request->tanggal);
            } elseif ($request->filled('bulan')) {
                $bulan = (int) $request->bulan;
                $tahun = $this->determineYear($ta, $bulan);
                $query->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun);
            }
        }

        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', $request->kelas_id);
        }

        if ($request->filled('guru_staf_id')) {
            $query->whereHas('guruMapel.guru', fn($q) => $q->where('id', $request->guru_staf_id));
        }

        if ($request->filled('mata_pelajaran_id')) {
            $query->where('mata_pelajaran_id', $request->mata_pelajaran_id);
        }

        if ($request->filled('search')) {
            $keyword = $request->search;
            $query->where(function($q) use ($keyword) {
                $q->whereHas('guruMapel.guru', function($qg) use ($keyword) {
                    $qg->where('nama', 'like', "%{$keyword}%");
                })
                ->orWhereHas('guruMapel.mapel', function($qm) use ($keyword) {
                    $qm->where('nama_mapel', 'like', "%{$keyword}%");
                })
                ->orWhereHas('getBySiswaDetil.siswa', function($qs) use ($keyword) {
                    $qs->where('nama_lengkap', 'like', "%{$keyword}%");
                })
                ->orWhere('materi', 'like', "%{$keyword}%");
            });
        }

        return $query;
    }

    private function determineYear($ta, $bulan)
    {
        $namaTA = str_replace([' Ganjil', ' Genap'], '', $ta->nama);
        $parts = explode('/', $namaTA);
        $tahunAwal = (int) $parts[0];
        $tahunAkhir = isset($parts[1]) ? (int) $parts[1] : $tahunAwal;

        if ($ta->semester === 'Ganjil') {
            return ($bulan >= 1 && $bulan <= 6) ? $tahunAkhir : $tahunAwal;
        } else {
            return ($bulan >= 7 && $bulan <= 12) ? $tahunAwal : $tahunAkhir;
        }
    }

    private function checkIsLibur($tanggal)
    {
        $dt = Carbon::parse($tanggal);
        $isWeekend = $dt->isWeekend();
        
        $taAktif = TahunAjaran::where('is_active', true)->first();
        $liburKalender = null;
        if ($taAktif) {
            $liburKalender = DB::table('kalender_akademik')
                ->where('tahun_ajaran_id', $taAktif->id)
                ->whereDate('tanggal_mulai', '<=', $tanggal)
                ->whereDate('tanggal_selesai', '>=', $tanggal)
                ->first();
        }

        if ($isWeekend || $liburKalender) {
            return [
                'is_libur' => true,
                'keterangan' => $liburKalender ? $liburKalender->keterangan : "Hari " . $dt->locale('id')->dayName . " (Libur Akhir Pekan)"
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

        $query = PresensiGuruMapel::with([
            'guruMapel.guru',
            'guruMapel.mapel',
            'guruMapel.jamMulai',
            'guruMapel.jamSelesai',
            'jamMasukDetail',
            'jamKeluarDetail',
            'kelas',
            'tahunAjaran'
        ])->whereHas('guruMapel.mapel', fn($q) => $q->where('is_active', 1));

        $query = $this->applyPresensiFilters($request, $query);

        $perHalaman = min((int) $request->get('per_page', 20), 100);
        $paginasi = $query->latest('tanggal')->paginate($perHalaman);

        return response()->json([
            'success' => true,
            'data' => PresensiGuruMapelResource::collection($paginasi),
            'meta' => [
                'current_page' => $paginasi->currentPage(),
                'last_page' => $paginasi->lastPage(),
                'per_page' => $paginasi->perPage(),
                'total' => $paginasi->total(),
                'from' => $paginasi->firstItem(),
                'to' => $paginasi->lastItem(),
                'path' => $request->url(),
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

        $relasi = GuruMapel::whereHas('mapel', fn($q) => $q->where('is_active', 1))
            ->findOrFail($request->input('guru_mapel_id'));

        $hariInput = Carbon::parse($tanggalInput)->locale('id')->dayName;

        if (strtolower($relasi->hari) !== strtolower($hariInput)) {
            return response()->json([
                'success' => false, 
                'message' => "Gagal simpan. Jadwal adalah hari {$relasi->hari}, sedangkan tanggal yang dipilih adalah hari {$hariInput}."
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $taAktif = TahunAjaran::where('is_active', true)->first();

        try {
            $presensi = DB::transaction(function () use ($request, $relasi, $taAktif, $tanggalInput) {
                $header = PresensiGuruMapel::updateOrCreate(
                    [
                        'guru_mapel_id' => $relasi->id, 
                        'tanggal' => $tanggalInput
                    ],
                    [
                        'kelas_id' => $relasi->kelas_id,
                        'mata_pelajaran_id' => $relasi->mata_pelajaran_id,
                        'tahun_ajaran_id' => $request->input('tahun_ajaran_id', $taAktif->id ?? $relasi->tahun_ajaran_id),
                        'jam_masuk' => $request->input('jam_masuk', $relasi->jam_mulai_id),
                        'jam_keluar' => $request->input('jam_keluar', $relasi->jam_selesai_id),
                        'materi' => $request->input('materi'),
                    ]
                );

                $inputPresensi = collect($request->input('presensi', []));
                $semuaSiswaIds = Siswa::where('kelas_id', $relasi->kelas_id)
                    ->where('is_active', true)
                    ->pluck('id');

                foreach ($semuaSiswaIds as $siswaId) {
                    $dataSiswa = $inputPresensi->firstWhere('siswa_id', $siswaId);
                    $header->getBySiswaDetil()->updateOrCreate(
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
                'getBySiswaDetil.siswa', 
                'guruMapel.guru', 
                'guruMapel.mapel', 
                'guruMapel.jamMulai', 
                'guruMapel.jamSelesai', 
                'jamMasukDetail', 
                'jamKeluarDetail', 
                'kelas', 
                'tahunAjaran'
            ])))
                ->additional(['success' => true, 'message' => 'Jurnal & Presensi berhasil disimpan.'])
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);

        } catch (Throwable $e) {
            Log::error('Admin Simpan Jurnal Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal simpan data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdatePresensiGuruMapelRequest $request, $id): JsonResponse
    {
        $presensi = PresensiGuruMapel::findOrFail($id);
        $this->authorize('update', $presensi);

        try {
            $updated = DB::transaction(function () use ($request, $presensi) {
                $input = array_filter($request->only([
                    'materi', 
                    'jam_masuk', 
                    'jam_keluar', 
                    'tanggal', 
                    'tahun_ajaran_id'
                ]), fn($value) => !is_null($value));

                $presensi->update($input);

                if ($request->has('presensi')) {
                    foreach ($request->input('presensi') as $item) {
                        $presensi->getBySiswaDetil()->where('siswa_id', $item['siswa_id'])
                            ->update([
                                'status' => $item['status'], 
                                'catatan' => $item['catatan'] ?? null
                            ]);
                    }
                }
                return $presensi->refresh()->load([
                    'getBySiswaDetil.siswa', 
                    'guruMapel.guru', 
                    'guruMapel.mapel', 
                    'guruMapel.jamMulai', 
                    'guruMapel.jamSelesai', 
                    'jamMasukDetail',
                    'jamKeluarDetail',
                    'kelas', 
                    'tahunAjaran'
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
        $presensi = PresensiGuruMapel::findOrFail($id);
        $this->authorize('delete', $presensi);

        try {
            DB::transaction(function() use ($presensi) {
                $presensi->getBySiswaDetil()->delete();
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
            'getBySiswaDetil.siswa',
            'guruMapel.guru',
            'guruMapel.mapel',
            'guruMapel.jamMulai',
            'guruMapel.jamSelesai',
            'jamMasukDetail',
            'jamKeluarDetail',
            'kelas',
            'tahunAjaran'
        ])->findOrFail($id);

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

            $jadwalCollection = $this->getQueryJadwal($namaHari, $request);
            $sudahAbsen = $this->getPresensiExisting($targetDate);

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
                    'path' => $request->url(),
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
        $query = GuruMapel::with(['mapel', 'kelas', 'guru', 'jamMulai', 'jamSelesai'])
            ->whereHas('mapel', fn($q) => $q->where('is_active', 1))
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
                return response()->json([
                    'success' => false, 
                    'message' => "Tidak dapat memuat data siswa. Tanggal tersebut adalah hari libur: {$cekLibur['keterangan']}.",
                    'error' => 'DATE_IS_HOLIDAY'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $jadwal = GuruMapel::with(['kelas', 'mapel', 'guru'])->findOrFail($guru_mapel_id);

            $presensiHeader = PresensiGuruMapel::where('guru_mapel_id', $guru_mapel_id)
                ->whereDate('tanggal', $targetDate)
                ->first();

            $siswa = Siswa::where('kelas_id', $jadwal->kelas_id)
                ->where('is_active', true)
                ->orderBy('nama_lengkap', 'asc')
                ->get();

            $detailExisting = $presensiHeader 
                ? $presensiHeader->getBySiswaDetil->keyBy('siswa_id') 
                : collect();

            return response()->json([
                'success' => true,
                'info' => [
                    'jurnal_id' => $presensiHeader->id ?? null,
                    'guru_mapel_id' => $jadwal->id,
                    'nama_guru' => $jadwal->guru->nama ?? '-',
                    'mata_pelajaran' => $jadwal->mapel->nama_mapel ?? '-',
                    'kelas' => $jadwal->kelas->nama_kelas ?? '-',
                    'tanggal' => $targetDate,
                    'sudah_isi_jurnal' => $presensiHeader ? true : false
                ],
                'data' => $siswa->map(function($s) use ($detailExisting) {
                    $detail = $detailExisting->get($s->id);
                    return [
                        'siswa_id' => $s->id,
                        'nama' => $s->nama_lengkap,
                        'nisn' => $s->nisn,
                        'status' => $detail ? $detail->status : null, 
                        'catatan' => $detail ? $detail->catatan : null
                    ];
                })
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
        $this->authorize('viewAny', PresensiGuruMapel::class);
        
        if (!$request->filled(['kelas_id', 'mata_pelajaran_id', 'guru_staf_id'])) {
            return response()->json([
                'success' => false, 
                'message' => 'Kelas, Mata Pelajaran, dan Guru wajib dipilih.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $isSinkron = DB::table('guru_mapel')
            ->where('kelas_id', $request->kelas_id)
            ->where('mata_pelajaran_id', $request->mata_pelajaran_id)
            ->where('guru_staf_id', $request->guru_staf_id)
            ->exists();

        if (!$isSinkron) {
            return response()->json([
                'success' => false, 
                'message' => 'Data tidak sinkron: Guru yang dipilih tidak mengampu mata pelajaran tersebut di kelas ini.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($error = $this->validateSemesterMonth($request)) {
            return response()->json(['success' => false, 'message' => $error], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $guruTarget = DB::table('guru_staf')->where('id', $request->guru_staf_id)->first();
        if (!$guruTarget) {
            return response()->json(['success' => false, 'message' => 'Data guru tidak tersedia.'], Response::HTTP_NOT_FOUND);
        }

        $namaKelas = Kelas::where('id', $request->kelas_id)->value('nama_kelas') ?? 'Unknown';
        $namaMapel = DB::table('mata_pelajaran')->where('id', $request->mata_pelajaran_id)->value('nama_mapel') ?? 'Mapel';

        $query = PresensiGuruMapel::query()->whereHas('guruMapel.mapel', fn($q) => $q->where('is_active', 1));
        $query = $this->applyPresensiFilters($request, $query);

        $ta = $request->filled('tahun_ajaran_id') 
            ? TahunAjaran::find($request->tahun_ajaran_id) 
            : TahunAjaran::where('is_active', true)->first();

        if (!$ta) {
            return response()->json(['success' => false, 'message' => 'Tahun Ajaran tidak ditemukan.'], Response::HTTP_NOT_FOUND);
        }

        $bulan = (int) $request->get('bulan', date('m'));
        $tahun = $this->determineYear($ta, $bulan);

        $labelWaktu = "Bulan-{$bulan}-Tahun-{$tahun}";
        $taClean = str_replace(['/', ' '], '_', $ta->nama);
        $filename = "Rekap_Presensi_Mapel_" . 
                    str_replace([' ', '/'], '_', $namaMapel) . "_" . 
                    str_replace([' ', '/'], '_', $namaKelas) . "_" . 
                    str_replace([' ', '/'], '_', $guruTarget->nama) . "_" . 
                    $labelWaktu . "_TA_" . 
                    $taClean . "_" . 
                    $ta->semester . ".xlsx";

        return Excel::download(
            new PresensiGuruMapelExport(
                $query, 
                "Bulan-{$bulan}-{$tahun}", 
                DB::table('profil_sekolah')->first(), 
                DB::table('data_kontak')->first(),
                (object)['nama' => $guruTarget->nama, 'nip' => $guruTarget->nip ?? '-'],
                $ta ? ($ta->nama . " (" . $ta->semester . ")") : 'Tahun Ajaran Tidak Aktif',
                true, 
                $bulan,
                $tahun,
                $ta->id ?? null
            ),
            $filename
        );
    }
}