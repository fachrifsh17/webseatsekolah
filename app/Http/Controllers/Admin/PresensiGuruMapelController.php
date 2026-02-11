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
            $query->whereHas('guruMapel', fn($q) => $q->where('guru_staf_id', $request->guru_staf_id));
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
                'total' => $paginasi->total(),
            ]
        ], Response::HTTP_OK);
    }

    public function store(StorePresensiGuruMapelRequest $request): JsonResponse
    {
        $this->authorize('create', PresensiGuruMapel::class);
        
        $relasi = GuruMapel::whereHas('mapel', fn($q) => $q->where('is_active', 1))
            ->findOrFail($request->input('guru_mapel_id'));

        $taAktif = TahunAjaran::where('is_active', true)->first();

        try {
            $presensi = DB::transaction(function () use ($request, $relasi, $taAktif) {
                $header = PresensiGuruMapel::updateOrCreate(
                    [
                        'guru_mapel_id' => $relasi->id, 
                        'tanggal' => $request->input('tanggal', Carbon::today()->toDateString())
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

    public function listJadwalHariIni(): JsonResponse
    {
        $this->authorize('viewAny', PresensiGuruMapel::class);

        $hariIni = Carbon::now('Asia/Jakarta')->locale('id')->dayName;

        $jadwal = GuruMapel::with(['mapel', 'kelas', 'guru', 'jamMulai', 'jamSelesai'])
            ->whereHas('mapel', fn($q) => $q->where('is_active', 1))
            ->where('hari', $hariIni)
            ->get();

        $sudahAbsen = PresensiGuruMapel::whereDate('tanggal', Carbon::today())
            ->get(['id', 'guru_mapel_id'])
            ->keyBy('guru_mapel_id');

        $data = $jadwal->map(function($j) use ($sudahAbsen) {
            $jurnal = $sudahAbsen->get($j->id);
            return [
                'guru_mapel_id' => $j->id,
                'jurnal_id' => $jurnal?->id,
                'nama_guru' => $j->guru?->nama,
                'mata_pelajaran' => $j->mapel?->nama_mapel,
                'kelas' => $j->kelas?->nama_kelas,
                'kelas_id' => $j->kelas_id,
                'jam' => "Jam Ke " . ($j->jamMulai?->jam_ke ?? '-') . " - " . ($j->jamSelesai?->jam_ke ?? '-'),
                'status' => $jurnal ? 'Sudah Absen' : 'Belum Absen'
            ];
        });

        return response()->json(['success' => true, 'data' => $data], Response::HTTP_OK);
    }

    public function getSiswaByJadwal($guru_mapel_id): JsonResponse
    {
        $this->authorize('create', PresensiGuruMapel::class);

        try {
            $jadwal = GuruMapel::with(['kelas', 'mapel', 'guru'])->findOrFail($guru_mapel_id);

            $siswa = Siswa::where('kelas_id', $jadwal->kelas_id)
                ->where('is_active', true)
                ->orderBy('nama_lengkap', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'info' => [
                    'guru_mapel_id' => $jadwal->id,
                    'nama_guru' => $jadwal->guru->nama ?? '-',
                    'mata_pelajaran' => $jadwal->mapel->nama_mapel ?? '-',
                    'kelas' => $jadwal->kelas->nama_kelas ?? '-',
                    'tanggal' => Carbon::today()->toDateString(),
                ],
                'data' => $siswa->map(fn($s) => [
                    'siswa_id' => $s->id,
                    'nama' => $s->nama_lengkap,
                    'nisn' => $s->nisn,
                    'status' => 'hadir',
                    'catatan' => null
                ])
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Get Siswa By Jadwal Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal memuat daftar siswa.',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', PresensiGuruMapel::class);
        
        if (!$request->filled('kelas_id')) {
            return response()->json(['success' => false, 'message' => 'Pilih kelas terlebih dahulu.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($error = $this->validateSemesterMonth($request)) {
            return response()->json(['success' => false, 'message' => $error], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $query = PresensiGuruMapel::query()->whereHas('guruMapel.mapel', fn($q) => $q->where('is_active', 1));
        $query = $this->applyPresensiFilters($request, $query);

        $ta = $request->filled('tahun_ajaran_id') 
            ? TahunAjaran::find($request->tahun_ajaran_id) 
            : TahunAjaran::where('is_active', true)->first();

        $bulan = (int) $request->get('bulan', date('m'));
        $tahun = $this->determineYear($ta, $bulan);
        
        $namaKelas = Kelas::where('id', $request->kelas_id)->value('nama_kelas') ?? 'Unknown';
        $guruTarget = $request->filled('guru_staf_id') ? DB::table('guru_staf')->where('id', $request->guru_staf_id)->first() : null;

        $taFile = $ta ? str_replace(['/', ' '], '-', $ta->nama) : 'TA-Unknown';
        $semesterFile = $ta ? $ta->semester : 'Semester-Unknown';
        $filename = "Rekap_Presensi_Mapel_{$namaKelas}_{$taFile}_{$semesterFile}_Bulan_{$bulan}.xlsx";

        return Excel::download(
            new PresensiGuruMapelExport(
                $query, 
                "Bulan-{$bulan}-{$tahun}", 
                DB::table('profil_sekolah')->first(), 
                DB::table('data_kontak')->first(),
                (object)['nama' => $guruTarget->nama ?? 'Semua Guru', 'nip' => $guruTarget->nip ?? '-'],
                $ta ? ($ta->nama . " (" . $ta->semester . ")") : 'Tahun Ajaran Tidak Aktif',
                true, 
                $bulan,
                $tahun
            ),
            $filename
        );
    }
}