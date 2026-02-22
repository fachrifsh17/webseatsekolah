<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\{PresensiGuruMapel, GuruMapel, Siswa, TahunAjaran, Kelas};
use App\Http\Requests\StorePresensiGuruMapelRequest;
use App\Http\Resources\PresensiGuruMapelResource;
use App\Exports\PresensiGuruMapelExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log};
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
        $user = $request->user();
        return $user->guruStaf?->id ?? null;
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

    private function checkIsLibur($tanggal, $taId)
    {
        $dt = Carbon::parse($tanggal);
        $isWeekend = $dt->isWeekend();
        
        $liburKalender = DB::table('kalender_akademik')
            ->where('tahun_ajaran_id', $taId)
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->first();

        if ($isWeekend || $liburKalender) {
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
        if (!$guruId) return response()->json(['success' => false, 'message' => 'Data Guru tidak ditemukan.'], Response::HTTP_FORBIDDEN);

        $now = Carbon::now('Asia/Jakarta');
        $targetDate = $now->toDateString();
        $namaHari = $now->locale('id')->dayName;
        $taAktif = TahunAjaran::where('is_active', true)->first();

        if (!$taAktif) {
            return response()->json(['success' => false, 'message' => 'Tidak ada Tahun Ajaran aktif.'], Response::HTTP_NOT_FOUND);
        }

        $cekLibur = $this->checkIsLibur($targetDate, $taAktif->id);
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
                'meta' => [
                    'total' => 0
                ],
                'message' => "Hari libur: " . $cekLibur['keterangan']
            ], Response::HTTP_OK);
        }

        $jadwal = GuruMapel::with(['mapel', 'kelas', 'jamMulai', 'jamSelesai'])
            ->where('guru_staf_id', $guruId)
            ->where('hari', $namaHari)
            ->where('tahun_ajaran_id', $taAktif->id)
            ->whereHas('mapel', fn($q) => $q->where('is_active', 1))
            ->whereHas('kelas', fn($q) => $q->where('is_active', 1))
            ->get();

        $sudahAbsen = PresensiGuruMapel::whereDate('tanggal', $targetDate)
            ->whereHas('guruMapel', fn($q) => $q->where('guru_staf_id', $guruId))
            ->get(['id', 'guru_mapel_id'])
            ->keyBy('guru_mapel_id');

        $data = $jadwal->map(function($item) use ($sudahAbsen) {
            $jurnal = $sudahAbsen->get($item->id);
            return [
                'guru_mapel_id'  => $item->id,
                'jurnal_id'      => $jurnal?->id,
                'mata_pelajaran' => $item->mapel?->nama_mapel,
                'kelas'          => $item->kelas?->nama_kelas,
                'jam'            => "Jam Ke " . ($item->jamMulai?->jam_ke ?? '-') . " - " . ($item->jamSelesai?->jam_ke ?? '-'),
                'status'         => $jurnal ? 'Sudah Absen' : 'Belum Absen'
            ];
        });

        return response()->json([
            'success' => true,
            'filter_info' => [
                'tanggal' => $targetDate,
                'hari'    => $namaHari,
                'is_hari_libur' => false,
                'keterangan_hari' => 'Hari Efektif',
                'total_jadwal' => $data->count()
            ],
            'data' => $data,
            'meta' => [
                'total' => $data->count()
            ]
        ], Response::HTTP_OK);
    }

    public function showJadwal($id, Request $request): JsonResponse
    {
        $guruId = $this->getGuruId($request);
        $taAktif = TahunAjaran::where('is_active', true)->first();

        try {
            $jadwal = GuruMapel::with(['mapel', 'kelas', 'jamMulai', 'jamSelesai'])
                ->where('guru_staf_id', $guruId)
                ->where('tahun_ajaran_id', $taAktif?->id)
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data'    => [
                    'id'             => $jadwal->id,
                    'mata_pelajaran' => $jadwal->mapel?->nama_mapel,
                    'kelas'          => $jadwal->kelas?->nama_kelas,
                    'hari'           => $jadwal->hari,
                    'jam_pelajaran'  => "Jam Ke " . ($jadwal->jamMulai?->jam_ke ?? '-') . " - " . ($jadwal->jamSelesai?->jam_ke ?? '-'),
                    'waktu'          => ($jadwal->jamMulai?->waktu_mulai ?? '-') . " s/d " . ($jadwal->jamSelesai?->waktu_selesai ?? '-'),
                ]
            ], Response::HTTP_OK);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Jadwal tidak ditemukan atau akses dilarang.'], Response::HTTP_NOT_FOUND);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan sistem.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getSiswaByJadwal(Request $request, $guru_mapel_id): JsonResponse
    {
        $guruId = $this->getGuruId($request);
        $taAktif = TahunAjaran::where('is_active', true)->first();

        try {
            $targetDate = $request->get('tanggal', Carbon::today()->toDateString());

            if ($taAktif) {
                $cekLibur = $this->checkIsLibur($targetDate, $taAktif->id);
                if ($cekLibur['is_libur']) {
                    return response()->json([
                        'success' => false, 
                        'message' => "Tidak dapat memuat data siswa. Tanggal tersebut adalah hari libur: {$cekLibur['keterangan']}.",
                        'error' => 'DATE_IS_HOLIDAY'
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            $jadwal = GuruMapel::with(['mapel', 'kelas'])
                ->where('guru_staf_id', $guruId)
                ->where('tahun_ajaran_id', $taAktif?->id)
                ->findOrFail($guru_mapel_id);

            $presensiHeader = PresensiGuruMapel::where('guru_mapel_id', $guru_mapel_id)
                ->whereDate('tanggal', $targetDate)
                ->first();

            $detailExisting = $presensiHeader ? $presensiHeader->getBySiswaDetil->keyBy('siswa_id') : collect();

            $siswa = Siswa::where('kelas_id', $jadwal->kelas_id)
                ->where('is_active', true)
                ->orderBy('nama_lengkap', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'info' => [
                    'jurnal_id'        => $presensiHeader?->id,
                    'guru_mapel_id'    => $jadwal->id,
                    'mata_pelajaran'   => $jadwal->mapel?->nama_mapel,
                    'kelas'            => $jadwal->kelas?->nama_kelas,
                    'tanggal'          => $targetDate,
                    'sudah_isi_jurnal' => !!$presensiHeader
                ],
                'data' => $siswa->map(fn($s) => [
                    'siswa_id' => $s->id,
                    'nama'     => $s->nama_lengkap,
                    'nisn'     => $s->nisn,
                    'status'   => $detailExisting->has($s->id) ? $detailExisting->get($s->id)->status : 'hadir',
                    'catatan'  => $detailExisting->has($s->id) ? $detailExisting->get($s->id)->catatan : null
                ])
            ], Response::HTTP_OK);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Jadwal tidak ditemukan atau akses dilarang.'], Response::HTTP_NOT_FOUND);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memuat data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $guruId = $this->getGuruId($request);
        $tanggal = $request->get('tanggal', date('Y-m-d'));
        $taAktif = TahunAjaran::where('is_active', true)->first();

        $query = PresensiGuruMapel::with([
            'mapel', 
            'kelas', 
            'getBySiswaDetil.siswa', 
            'guruMapel.jamMulai', 
            'guruMapel.jamSelesai',
            'jamMasukDetail',
            'jamKeluarDetail'
        ])
        ->whereHas('guruMapel', fn($q) => $q->where('guru_staf_id', $guruId))
        ->where('tahun_ajaran_id', $taAktif?->id)
        ->whereDate('tanggal', $tanggal)
        ->latest();

        $paginator = $query->paginate($request->get('per_page', 10));
        $paginationArray = $paginator->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Riwayat mengajar tanggal ' . $tanggal,
            'data'    => PresensiGuruMapelResource::collection($paginator),
            'meta'    => [
                'current_page' => $paginationArray['current_page'],
                'last_page'    => $paginationArray['last_page'],
                'per_page'     => $paginationArray['per_page'],
                'total'        => $paginationArray['total'],
                'from'         => $paginationArray['from'],
                'to'           => $paginationArray['to'],
                'path'         => $paginationArray['path'],
                'next_page_url'=> $paginationArray['next_page_url'],
                'prev_page_url'=> $paginationArray['prev_page_url'],
                'links'        => array_map(function ($link) {
                    return [
                        'url'    => $link['url'],
                        'label'  => $link['label'],
                        'page'   => is_numeric($link['label']) ? (int) $link['label'] : null,
                        'active' => $link['active'],
                    ];
                }, $paginationArray['links']),
            ]
        ], Response::HTTP_OK);
    }

    public function show($id, Request $request): JsonResponse
    {
        $guruId = $this->getGuruId($request);
        
        try {
            $presensi = PresensiGuruMapel::with([
                'mapel', 
                'kelas', 
                'getBySiswaDetil.siswa', 
                'guruMapel.jamMulai', 
                'guruMapel.jamSelesai',
                'jamMasukDetail',
                'jamKeluarDetail'
            ])
            ->whereHas('guruMapel', fn($q) => $q->where('guru_staf_id', $guruId))
            ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data'    => new PresensiGuruMapelResource($presensi)
            ], Response::HTTP_OK);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Data presensi tidak ditemukan.'], Response::HTTP_NOT_FOUND);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan sistem.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StorePresensiGuruMapelRequest $request): JsonResponse
    {
        $now = Carbon::now('Asia/Jakarta');
        $taAktif = TahunAjaran::where('is_active', true)->firstOrFail();
        $guruId = $this->getGuruId($request);

        $cekLibur = $this->checkIsLibur($now->toDateString(), $taAktif->id);
        if ($cekLibur['is_libur']) {
            return response()->json([
                'success' => false,
                'message' => "Gagal simpan. Tanggal tersebut adalah hari libur: {$cekLibur['keterangan']}."
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $relasi = GuruMapel::with(['jamMulai', 'jamSelesai', 'kelas', 'mapel'])
                ->where('guru_staf_id', $guruId)
                ->where('tahun_ajaran_id', $taAktif->id)
                ->whereHas('mapel', fn($q) => $q->where('is_active', 1))
                ->findOrFail($request->input('guru_mapel_id'));

            $validation = $this->validateTeachingTime($relasi, $now);
            if (!$validation['status']) {
                return response()->json(['success' => false, 'message' => $validation['message']], Response::HTTP_FORBIDDEN);
            }

            $siswaValidation = $this->validateSiswaAttendance($relasi->kelas_id, $request->input('presensi', []));
            if (!$siswaValidation['status']) {
                return response()->json(['success' => false, 'message' => $siswaValidation['message']], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $presensi = $this->performStoreTransaction($relasi, $taAktif, $request, $now->toDateString());

            return (new PresensiGuruMapelResource($presensi->load([
                'getBySiswaDetil.siswa', 
                'mapel', 
                'kelas', 
                'guruMapel.jamMulai', 
                'guruMapel.jamSelesai',
                'jamMasukDetail',
                'jamKeluarDetail'
            ])))
            ->additional(['success' => true, 'message' => 'Jurnal & Presensi berhasil disimpan.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);

        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Jadwal tidak ditemukan atau akses dilarang.'], Response::HTTP_NOT_FOUND);
        } catch (Throwable $e) {
            Log::error('Guru Store Presensi Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal simpan data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function validateTeachingTime($relasi, Carbon $now): array
    {
        $jamSekarang = $now->toTimeString();
        $hariIni = $now->locale('id')->dayName;

        if (strcasecmp(trim($relasi->hari), trim($hariIni)) !== 0) {
            return ['status' => false, 'message' => "Gagal simpan. Jadwal adalah hari {$relasi->hari}, sedangkan hari ini adalah hari $hariIni."];
        }

        if ($jamSekarang < $relasi->jamMulai?->waktu_mulai) {
            return ['status' => false, 'message' => "Belum waktunya mengisi presensi. Jadwal dimulai pukul " . $relasi->jamMulai?->waktu_mulai];
        }

        if ($jamSekarang > $relasi->jamSelesai?->waktu_selesai) {
            return ['status' => false, 'message' => "Batas waktu pengisian telah berakhir. Jadwal selesai pukul " . $relasi->jamSelesai?->waktu_selesai];
        }

        return ['status' => true];
    }

    private function validateSiswaAttendance($kelasId, array $inputPresensi): array
    {
        $validSiswaIds = Siswa::where('kelas_id', $kelasId)
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();

        $inputSiswaIds = collect($inputPresensi)->pluck('siswa_id')->toArray();
        $invalidIds = array_diff($inputSiswaIds, $validSiswaIds);

        if (!empty($invalidIds) || count($inputSiswaIds) !== count($validSiswaIds)) {
            return ['status' => false, 'message' => 'Daftar siswa tidak valid atau tidak lengkap untuk kelas ini.'];
        }

        return ['status' => true];
    }

    private function performStoreTransaction($relasi, $taAktif, $request, $tanggal)
    {
        return DB::transaction(function () use ($relasi, $taAktif, $request, $tanggal) {
            $header = PresensiGuruMapel::updateOrCreate(
                [
                    'guru_mapel_id' => $relasi->id, 
                    'tanggal'       => $tanggal
                ],
                [
                    'kelas_id'          => $relasi->kelas_id,
                    'mata_pelajaran_id' => $relasi->mata_pelajaran_id,
                    'tahun_ajaran_id'   => $taAktif->id,
                    'jam_masuk'         => $relasi->jam_mulai_id, 
                    'jam_keluar'        => $relasi->jam_selesai_id,
                    'materi'            => $request->input('materi'),
                ]
            );

            foreach ($request->input('presensi', []) as $dataSiswa) {
                $header->getBySiswaDetil()->updateOrCreate(
                    ['siswa_id' => $dataSiswa['siswa_id']],
                    [
                        'status'  => $dataSiswa['status'],
                        'catatan' => $dataSiswa['catatan'] ?? null
                    ]
                );
            }

            return $header;
        });
    }

    public function export(Request $request)
    {
        $guruId = $this->getGuruId($request);
        
        if (!$request->filled('guru_mapel_id')) {
            return response()->json(['success' => false, 'message' => 'Pilih mata pelajaran dan kelas terlebih dahulu.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $jadwal = GuruMapel::with(['mapel', 'kelas'])
                ->where('guru_staf_id', $guruId)
                ->findOrFail($request->guru_mapel_id);

            $ta = $request->filled('tahun_ajaran_id') 
                ? TahunAjaran::find($request->tahun_ajaran_id) 
                : TahunAjaran::where('is_active', true)->first();

            if (!$ta) {
                return response()->json(['success' => false, 'message' => 'Tahun Ajaran tidak ditemukan.'], Response::HTTP_NOT_FOUND);
            }

            $bulan = (int) $request->get('bulan', date('m'));
            $tahun = $this->determineYear($ta, $bulan);

            $query = PresensiGuruMapel::query()
                ->where('guru_mapel_id', $jadwal->id)
                ->whereMonth('tanggal', $bulan)
                ->whereYear('tanggal', $tahun);

            $namaKelas = $jadwal->kelas?->nama_kelas ?? 'Unknown';
            $namaMapel = $jadwal->mapel?->nama_mapel ?? 'Mapel';
            $user = $request->user();
            $namaGuru = $user->guruStaf?->nama ?? 'Guru';

            $labelWaktu = "Bulan-{$bulan}-Tahun-{$tahun}";
            $taClean = str_replace(['/', ' '], '_', $ta->nama);
            $filename = "Rekap_Presensi_Mapel_" . 
                        str_replace([' ', '/'], '_', $namaMapel) . "_" . 
                        str_replace([' ', '/'], '_', $namaKelas) . "_" . 
                        str_replace([' ', '/'], '_', $namaGuru) . "_" . 
                        $labelWaktu . "_TA_" . 
                        $taClean . "_" . 
                        $ta->semester . ".xlsx";

            $profilSekolah = DB::table('profil_sekolah')->first(); 
            $dataKontak = DB::table('data_kontak')->first();

            return Excel::download(
                new PresensiGuruMapelExport(
                    $query, 
                    "Bulan-{$bulan}-{$tahun}", 
                    $profilSekolah, 
                    $dataKontak, 
                    (object)['nama' => $namaGuru, 'nip' => $user->guruStaf?->nip ?? '-'],
                    $ta ? ($ta->nama . " (" . $ta->semester . ")") : 'Tahun Ajaran Tidak Aktif',
                    true, 
                    $bulan,
                    $tahun,
                    $ta->id ?? null
                ),
                $filename
            );
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Jadwal tidak ditemukan.'], Response::HTTP_NOT_FOUND);
        } catch (Throwable $e) {
            Log::error('Guru Export Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengekspor data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}