<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\{PresensiGuruMapel, GuruMapel, Siswa, TahunAjaran, Kelas};
use App\Http\Requests\StorePresensiGuruMapelRequest;
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
        $taAktif = TahunAjaran::where('is_active', true)->first();

        if (!$taAktif) return response()->json(['success' => false, 'message' => 'Tidak ada tahun ajaran aktif.'], Response::HTTP_NOT_FOUND);

        $cekLibur = $this->checkIsLibur($targetDate, $taAktif->id);

        $jadwal = GuruMapel::with(['mapel', 'kelas', 'jamMulai', 'jamSelesai'])
            ->leftJoin('jam_sekolah', 'guru_mapel.jam_mulai_id', '=', 'jam_sekolah.id')
            ->where([
                'guru_mapel.guru_staf_id' => $guruId, 
                'guru_mapel.hari' => $now->locale('id')->dayName, 
                'guru_mapel.tahun_ajaran_id' => $taAktif->id
            ])
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
        $taId = $request->get('tahun_ajaran_id') ?: TahunAjaran::where('is_active', true)->first()?->id;
        $tanggal = $request->get('tanggal', date('Y-m-d'));

        $query = PresensiGuruMapel::with(['mapel', 'kelas', 'guruMapel.jamMulai', 'guruMapel.jamSelesai'])
            ->whereHas('guruMapel', fn($q) => $q->where('guru_staf_id', $this->getGuruId($request)))
            ->where(['tahun_ajaran_id' => $taId])
            ->whereDate('tanggal', $tanggal)
            ->latest();

        $paginator = $query->paginate($request->get('per_page', 10));
        $data = $paginator->getCollection()->map(function($item) use ($taId) {
            $details = $item->getBySiswaDetil()->whereHas('siswa.riwayatKelas', fn($q) => 
                $q->where([
                    'kelas_id' => $item->kelas_id, 
                    'tahun_ajaran_id' => $taId,
                ])
            )->get();

            return [
                'id' => $item->id,
                'tanggal' => Carbon::parse($item->tanggal)->format('Y-m-d'),
                'kelas' => $item->kelas?->nama_kelas,
                'mapel' => $item->mapel?->nama_mapel,
                'jam' => "Jam " . ($item->guruMapel?->jamMulai?->jam_ke ?? '-') . " - " . ($item->guruMapel?->jamSelesai?->jam_ke ?? '-'),
                'materi' => $item->materi,
                'ringkasan' => [
                    'hadir' => $details->where('status', 'hadir')->count(),
                    'izin'  => $details->where('status', 'izin')->count(),
                    'sakit' => $details->where('status', 'sakit')->count(),
                    'alfa'  => $details->where('status', 'alfa')->count(),
                ],
                'updated_at' => $item->updated_at->format('d-m-Y H:i')
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Riwayat Mengajar Tanggal ' . $tanggal,
            'data'    => $data,
            'meta'    => $this->formatPaginationMeta($paginator->toArray())
        ], Response::HTTP_OK);
    }

    public function getSiswaByJadwal(Request $request, $guru_mapel_id): JsonResponse
    {
        try {
            $taAktif = TahunAjaran::where('is_active', true)->first();
            $targetDate = $request->get('tanggal', Carbon::today()->toDateString());
            
            $jadwal = GuruMapel::with(['mapel', 'kelas'])
                ->where('guru_staf_id', $this->getGuruId($request))
                ->findOrFail($guru_mapel_id);

            $siswaList = Siswa::whereHas('riwayatKelas', fn($q) => $q->where([
                    'kelas_id' => $jadwal->kelas_id, 
                    'tahun_ajaran_id' => $jadwal->tahun_ajaran_id,
                ]))
                ->orderBy('nama_lengkap', 'asc')->get();

            $header = PresensiGuruMapel::with('getBySiswaDetil')
                ->where(['guru_mapel_id' => $guru_mapel_id])
                ->whereDate('tanggal', $targetDate)
                ->first();

            $detailExisting = $header ? $header->getBySiswaDetil->keyBy('siswa_id') : collect();

            $dataSiswa = $siswaList->map(function($s) use ($detailExisting) {
                $detail = $detailExisting->get($s->id);
                return [
                    'siswa_id' => $s->id,
                    'nama'     => $s->nama_lengkap . ($s->is_active ? '' : ' (Non-Aktif/Alumni)'),
                    'status'   => $detail ? $detail->status : null,
                    'catatan'  => $detail ? $detail->catatan : null
                ];
            });

            return response()->json([
                'success' => true,
                'info' => [
                    'jurnal_id' => $header?->id, 
                    'mata_pelajaran' => $jadwal->mapel?->nama_mapel, 
                    'kelas' => $jadwal->kelas?->nama_kelas, 
                    'tanggal' => $targetDate
                ],
                'data' => $dataSiswa
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memuat data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StorePresensiGuruMapelRequest $request): JsonResponse
    {
        $now = Carbon::now('Asia/Jakarta');
        $currentTime = $now->toTimeString(); 
        $taAktif = TahunAjaran::where('is_active', true)->firstOrFail();
        
        try {
            $relasi = GuruMapel::with(['jamMulai', 'jamSelesai'])
                ->where('guru_staf_id', $this->getGuruId($request))
                ->findOrFail($request->input('guru_mapel_id'));

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

            $inputPresensi = collect($request->input('presensi', []));
            $inputSiswaIds = $inputPresensi->pluck('siswa_id')->toArray();

            $validSiswaIds = Siswa::whereHas('riwayatKelas', fn($q) => 
                $q->where([
                    'kelas_id' => $relasi->kelas_id, 
                    'tahun_ajaran_id' => $relasi->tahun_ajaran_id,
                ])
            )->whereIn('id', $inputSiswaIds)->pluck('id')->toArray();

            $invalidIds = array_diff($inputSiswaIds, $validSiswaIds);

            if (!empty($invalidIds)) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Terdapat siswa yang tidak terdaftar di kelas ini.',
                    'invalid_siswa_ids' => array_values($invalidIds)
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            DB::transaction(function () use ($relasi, $taAktif, $request, $now, $inputPresensi) {
                $header = PresensiGuruMapel::updateOrCreate(
                    ['guru_mapel_id' => $relasi->id, 'tanggal' => $now->toDateString()],
                    [
                        'kelas_id' => $relasi->kelas_id, 
                        'mata_pelajaran_id' => $relasi->mata_pelajaran_id, 
                        'tahun_ajaran_id' => $relasi->tahun_ajaran_id, 
                        'jam_masuk' => $relasi->jam_mulai_id, 
                        'jam_keluar' => $relasi->jam_selesai_id, 
                        'materi' => $request->input('materi')
                    ]
                );

                foreach ($inputPresensi as $ds) {
                    $header->getBySiswaDetil()->updateOrCreate(
                        ['siswa_id' => $ds['siswa_id']], 
                        ['status' => $ds['status'], 'catatan' => $ds['catatan'] ?? null]
                    );
                }
            });

            return response()->json(['success' => true, 'message' => 'Data presensi berhasil disimpan.'], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal simpan data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        $jadwal = GuruMapel::with(['mapel', 'kelas'])
            ->where('guru_staf_id', $this->getGuruId($request))
            ->findOrFail($request->guru_mapel_id);
            
        $ta = TahunAjaran::find($jadwal->tahun_ajaran_id);
        $bulan = (int) $request->get('bulan', date('m'));
        $tahun = $this->determineYear($ta, $bulan);

        $query = PresensiGuruMapel::query()
            ->where(['guru_mapel_id' => $jadwal->id])
            ->whereMonth('tanggal', $bulan)
            ->whereYear('tanggal', $tahun);

        return Excel::download(
            new PresensiGuruMapelExport(
                $query, 
                "Bulan-{$bulan}-{$tahun}", 
                DB::table('profil_sekolah')->first(), 
                DB::table('data_kontak')->first(), 
                (object)['nama' => $request->user()->guruStaf?->nama ?? $request->user()->name, 'nip' => $request->user()->guruStaf?->nip],
                $ta->nama . " (" . $ta->semester . ")", 
                true, 
                $bulan, 
                $tahun, 
                $ta->id,
                'guru'
            ),
            "REKAP_PRESENSI_" . strtoupper(str_replace(' ', '_', $jadwal->mapel->nama_mapel)) . "_" . strtoupper(str_replace(' ', '_', $jadwal->kelas->nama_kelas)) . "_{$bulan}_{$tahun}.xlsx"
        );
    }

    private function formatPaginationMeta(array $paginated)
    {
        return [
            'current_page' => $paginated['current_page'],
            'last_page'    => $paginated['last_page'],
            'per_page'     => $paginated['per_page'],
            'total'        => $paginated['total'],
        ];
    }
}