<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\{PresensiGuruMapel, GuruMapel, Siswa, TahunAjaran, Kelas};
use App\Http\Requests\StorePresensiGuruMapelRequest;
use App\Exports\PresensiGuruMapelExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log, Validator};
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

    private function determineYear($ta, $bulan)
    {
        $namaTA = str_replace([' Ganjil', ' Genap'], '', $ta->nama);
        $parts = explode('/', $namaTA);
        $tahunAwal = (int) $parts[0];
        $tahunAkhir = $parts[1] ?? $tahunAwal;

        if ($ta->semester === 'Ganjil') {
            return ($bulan >= 7 && $bulan <= 12) ? $tahunAwal : $tahunAkhir;
        }
        return ($bulan >= 1 && $bulan <= 6) ? $tahunAkhir : $tahunAwal;
    }

    private function getSiswaDariRiwayat($kelasId, $taId)
    {
        return Siswa::where('is_active', true)
            ->whereHas('riwayatKelas', function ($q) use ($kelasId, $taId) {
                $q->where('kelas_id', $kelasId)
                  ->where('tahun_ajaran_id', $taId)
                  ->where('is_active', true);
            })
            ->orderBy('nama_lengkap', 'asc')
            ->get();
    }

    private function checkIsLibur($tanggal, $taId)
    {
        $dt = Carbon::parse($tanggal);
        $liburKalender = DB::table('kalender_akademik')
            ->where('tahun_ajaran_id', $taId)
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->first();

        if ($dt->isWeekend() || $liburKalender) {
            return [
                'is_libur' => true,
                'keterangan' => $liburKalender ? $liburKalender->keterangan : "Hari " . $dt->locale('id')->dayName . " (Libur Akhir Pekan)"
            ];
        }
        return ['is_libur' => false];
    }

    public function listJadwalHariIni(Request $request): JsonResponse
    {
        $guruId = $this->getGuruId($request);
        if (!$guruId) return response()->json(['success' => false, 'message' => 'Data guru tidak ditemukan.'], Response::HTTP_FORBIDDEN);

        $now = Carbon::now('Asia/Jakarta');
        $currentTime = $now->toTimeString();
        $targetDate = $now->toDateString();
        $taAktif = TahunAjaran::where('is_active', true)->first() ?: TahunAjaran::orderBy('id', 'desc')->first();

        if (!$taAktif) return response()->json(['success' => false, 'message' => 'Tidak ada tahun ajaran ditemukan.'], Response::HTTP_NOT_FOUND);

        $cekLibur = $this->checkIsLibur($targetDate, $taAktif->id);

        $jadwal = GuruMapel::with(['mapel', 'kelas', 'jamMulai', 'jamSelesai'])
            ->leftJoin('jam_sekolah', 'guru_mapel.jam_mulai_id', '=', 'jam_sekolah.id')
            ->where([
                'guru_mapel.guru_staf_id' => $guruId, 
                'guru_mapel.hari' => $now->locale('id')->dayName, 
                'guru_mapel.tahun_ajaran_id' => $taAktif->id
            ])
            ->whereHas('mapel', fn($q) => $q->where('is_active', 1))
            ->orderBy('jam_sekolah.jam_ke', 'asc')
            ->select('guru_mapel.*')
            ->get();

        $sudahAbsen = PresensiGuruMapel::whereDate('tanggal', $targetDate)
            ->whereHas('guruMapel', fn($q) => $q->where('guru_staf_id', $guruId))
            ->get(['id', 'guru_mapel_id'])->keyBy('guru_mapel_id');

        $data = $jadwal->map(function($item) use ($sudahAbsen, $currentTime) {
            $mulai = $item->jamMulai?->waktu_mulai;
            $selesai = $item->jamSelesai?->waktu_selesai;
            $isOpen = ($currentTime >= $mulai && $currentTime <= $selesai);

            return [
                'guru_mapel_id'  => $item->id,
                'jurnal_id'      => $sudahAbsen->get($item->id)?->id,
                'mata_pelajaran' => $item->mapel?->nama_mapel,
                'kelas'          => $item->kelas?->nama_kelas,
                'jam'            => "Jam Ke " . ($item->jamMulai?->jam_ke ?? '-') . " - " . ($item->jamSelesai?->jam_ke ?? '-'),
                'rentang_waktu'  => ($mulai ? Carbon::parse($mulai)->format('H:i') : '-') . " - " . ($selesai ? Carbon::parse($selesai)->format('H:i') : '-'),
                'status'         => $sudahAbsen->has($item->id) ? 'Sudah Absen' : 'Belum Absen',
                'is_open'        => $isOpen
            ];
        });

        return response()->json([
            'success' => true,
            'filter_info' => [
                'tanggal' => $targetDate,
                'hari'    => $now->locale('id')->dayName,
                'is_hari_libur' => $cekLibur['is_libur'],
                'keterangan_hari' => $cekLibur['is_libur'] ? $cekLibur['keterangan'] : 'Hari Efektif',
                'total_jadwal' => $data->count()
            ],
            'data' => $data
        ], Response::HTTP_OK);
    }

   public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bulan' => 'nullable|integer|between:1,12',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Format bulan tidak valid.'], Response::HTTP_BAD_REQUEST);
        }

        $guruId = $this->getGuruId($request);
        $taAktif = TahunAjaran::where('is_active', true)->first() ?: TahunAjaran::orderBy('id', 'desc')->first();
        $taId = $request->get('tahun_ajaran_id') ?: $taAktif?->id;
        
        $paginator = $this->getPresensiQuery($request, $guruId, $taId)
            ->paginate($request->get('per_page', 10));
        
        $data = $paginator->getCollection()->map(fn($item) => $this->formatPresensiItem($item));

        return response()->json([
            'success' => true,
            'message' => 'Riwayat Jurnal Mengajar Anda',
            'data'    => $data,
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'path'         => $paginator->path(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
        ], Response::HTTP_OK);
    }

    private function getPresensiQuery(Request $request, $guruId, $taId)
    {
        return PresensiGuruMapel::with(['mapel', 'kelas', 'guruMapel.jamMulai', 'guruMapel.jamSelesai', 'getBySiswaDetil'])
            ->whereHas('guruMapel', fn($q) => $q->where('guru_staf_id', $guruId))
            ->when($taId, fn($q) => $q->where('tahun_ajaran_id', $taId))
            ->when($request->filled('tanggal'), fn($q) => $q->whereDate('tanggal', $request->tanggal))
            ->when($request->filled('bulan'), fn($q) => $q->whereMonth('tanggal', $request->bulan))
            ->when($request->filled('kelas_id'), fn($q) => $q->where('kelas_id', $request->kelas_id))
            ->latest('tanggal')
            ->latest('created_at');
    }

    private function formatPresensiItem($item)
    {
        return [
            'id' => $item->id,
            'tanggal' => Carbon::parse($item->tanggal)->format('Y-m-d'),
            'hari' => Carbon::parse($item->tanggal)->locale('id')->dayName,
            'kelas' => $item->kelas?->nama_kelas,
            'mapel' => $item->mapel?->nama_mapel,
            'jam' => ($item->guruMapel?->jamMulai?->jam_ke ?? '-') . " - " . ($item->guruMapel?->jamSelesai?->jam_ke ?? '-'),
            'materi' => $item->materi,
            'ringkasan' => $this->calculateRingkasan($item),
            'waktu_input' => $item->created_at->format('d/m/Y H:i')
        ];
    }

    private function calculateRingkasan($item)
    {
        $details = $item->getBySiswaDetil;
        $totalSiswaSah = $this->getSiswaDariRiwayat($item->kelas_id, $item->tahun_ajaran_id)->count();

        $hadir = $details->where('status', 'hadir')->count();
        $izin  = $details->where('status', 'izin')->count();
        $sakit = $details->where('status', 'sakit')->count();
        $alfa  = $details->where('status', 'alfa')->count();

        $totalTerinput = $hadir + $izin + $sakit + $alfa;
        
        if ($totalTerinput < $totalSiswaSah) {
            $alfa += ($totalSiswaSah - $totalTerinput);
        }

        return [
            'hadir' => $hadir,
            'izin'  => $izin,
            'sakit' => $sakit,
            'alfa'  => $alfa,
        ];
    }
    public function store(StorePresensiGuruMapelRequest $request): JsonResponse
    {
        $now = Carbon::now('Asia/Jakarta');
        $currentTime = $now->toTimeString(); 
        $tanggalInput = $now->toDateString();
        
        try {
            $relasi = GuruMapel::with(['jamMulai', 'jamSelesai'])
                ->where('guru_staf_id', $this->getGuruId($request))
                ->find($request->input('guru_mapel_id'));

            if (!$relasi) {
                return response()->json(['success' => false, 'message' => 'Jadwal tidak valid.'], Response::HTTP_NOT_FOUND);
            }

            if ($relasi->hari !== $now->locale('id')->dayName) {
                return response()->json([
                    'success' => false,
                    'message' => "Hanya dapat mengisi presensi pada hari {$relasi->hari}."
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $waktuMulai = $relasi->jamMulai?->waktu_mulai;
            $waktuSelesai = $relasi->jamSelesai?->waktu_selesai;

            if ($currentTime < $waktuMulai || $currentTime > $waktuSelesai) {
                $labelMulai = Carbon::parse($waktuMulai)->format('H:i');
                $labelSelesai = Carbon::parse($waktuSelesai)->format('H:i');
                
                return response()->json([
                    'success' => false,
                    'message' => "Presensi hanya dapat diisi pada jam pelajaran: {$labelMulai} - {$labelSelesai}."
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $siswaSahIds = $this->getSiswaDariRiwayat($relasi->kelas_id, $relasi->tahun_ajaran_id)->pluck('id')->toArray();
            $inputPresensiRaw = collect($request->input('presensi', []));

            DB::transaction(function () use ($relasi, $request, $tanggalInput, $siswaSahIds, $inputPresensiRaw) {
                $header = PresensiGuruMapel::updateOrCreate(
                    ['guru_mapel_id' => $relasi->id, 'tanggal' => $tanggalInput],
                    [
                        'kelas_id' => $relasi->kelas_id, 
                        'mata_pelajaran_id' => $relasi->mata_pelajaran_id, 
                        'tahun_ajaran_id' => $relasi->tahun_ajaran_id, 
                        'jam_masuk' => $relasi->jam_mulai_id, 
                        'jam_keluar' => $relasi->jam_selesai_id, 
                        'materi' => $request->input('materi')
                    ]
                );

                foreach ($siswaSahIds as $siswaId) {
                    $ds = $inputPresensiRaw->firstWhere('siswa_id', $siswaId);
                    $header->getBySiswaDetil()->updateOrCreate(
                        ['siswa_id' => $siswaId], 
                        [
                            'status' => $ds['status'] ?? 'hadir', 
                            'catatan' => $ds['catatan'] ?? null
                        ]
                    );
                }
            });

            return response()->json(['success' => true, 'message' => 'Data presensi berhasil disimpan.'], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal simpan data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $guruId = $this->getGuruId($request);
            $presensi = PresensiGuruMapel::with(['mapel', 'kelas', 'guruMapel.jamMulai', 'guruMapel.jamSelesai', 'getBySiswaDetil.siswa'])
                ->whereHas('guruMapel', fn($q) => $q->where('guru_staf_id', $guruId))
                ->find($id);

            if (!$presensi) {
                return response()->json(['success' => false, 'message' => 'Data presensi tidak ditemukan.'], Response::HTTP_NOT_FOUND);
            }

            $dataSiswa = $presensi->getBySiswaDetil->map(fn($d) => [
                'siswa_id' => $d->siswa_id,
                'nama' => $d->siswa?->nama_lengkap,
                'status' => $d->status,
                'catatan' => $d->catatan
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $presensi->id,
                    'tanggal' => Carbon::parse($presensi->tanggal)->format('Y-m-d'),
                    'mata_pelajaran' => $presensi->mapel?->nama_mapel,
                    'kelas' => $presensi->kelas?->nama_kelas,
                    'materi' => $presensi->materi,
                    'jam' => ($presensi->guruMapel?->jamMulai?->jam_ke ?? '-') . " - " . ($presensi->guruMapel?->jamSelesai?->jam_ke ?? '-'),
                    'presensi' => $dataSiswa
                ]
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memuat detail.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bulan' => 'required|integer|between:1,12',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Pilih bulan yang valid (1-12).'], Response::HTTP_BAD_REQUEST);
        }

        $taAktif = TahunAjaran::where('is_active', true)->first() ?: TahunAjaran::orderBy('id', 'desc')->first();
        $taId = $request->get('tahun_ajaran_id') ?: $taAktif?->id;

        if (!$taId) {
            return response()->json(['success' => false, 'message' => 'Tahun Ajaran tidak ditemukan.'], Response::HTTP_NOT_FOUND);
        }

        $guruId = $this->getGuruId($request);
        $bulan = (int) $request->get('bulan');
        $ta = TahunAjaran::find($taId);
        $tahun = $this->determineYear($ta, $bulan);

        $query = PresensiGuruMapel::query()
            ->where('tahun_ajaran_id', $taId)
            ->whereMonth('tanggal', $bulan)
            ->whereYear('tanggal', $tahun)
            ->whereHas('guruMapel', fn($q) => $q->where('guru_staf_id', $guruId));

        if ($request->filled('guru_mapel_id')) {
            $query->where('guru_mapel_id', $request->guru_mapel_id);
            $jadwal = GuruMapel::with(['mapel', 'kelas'])->find($request->guru_mapel_id);
        } else if ($request->filled(['kelas_id', 'mata_pelajaran_id'])) {
            $query->where('kelas_id', $request->kelas_id)
                  ->where('mata_pelajaran_id', $request->mata_pelajaran_id);
            
            $jadwal = GuruMapel::with(['mapel', 'kelas'])
                ->where([
                    'guru_staf_id' => $guruId,
                    'kelas_id' => $request->kelas_id,
                    'mata_pelajaran_id' => $request->mata_pelajaran_id,
                    'tahun_ajaran_id' => $taId
                ])->first();
        } else {
            return response()->json(['success' => false, 'message' => 'Parameter filter tidak lengkap.'], Response::HTTP_BAD_REQUEST);
        }

        if (!$jadwal) {
            return response()->json(['success' => false, 'message' => 'Jadwal tidak ditemukan.'], Response::HTTP_NOT_FOUND);
        }

        $fileName = strtoupper("REKAP_PRESENSI_" . str_replace(' ', '_', $jadwal->mapel->nama_mapel) . "_" . str_replace(' ', '_', $jadwal->kelas->nama_kelas) . "_{$bulan}_{$tahun}.xlsx");

        return Excel::download(
            new PresensiGuruMapelExport(
                $query, 
                "Bulan-{$bulan}-{$tahun}", 
                DB::table('profil_sekolah')->first(), 
                DB::table('data_kontak')->first(), 
                (object)[
                    'nama' => $request->user()->guruStaf?->nama_lengkap ?? $request->user()->name, 
                    'nip' => $request->user()->guruStaf?->nip
                ],
                $ta, 
                true, 
                $bulan, 
                $tahun, 
                $taId,
                'guru'
            ),
            $fileName
        );
    }
}