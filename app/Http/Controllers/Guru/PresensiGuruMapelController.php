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

    private function isDayOff($date): bool
    {
        $libur = DB::table('kalender_akademik')
            ->where('kategori', 'Libur')
            ->whereDate('tanggal_mulai', '<=', $date)
            ->whereDate('tanggal_selesai', '>=', $date)
            ->first();
        
        return $libur || date('N', strtotime($date)) >= 6;
    }

    public function listJadwalHariIni(Request $request): JsonResponse
    {
        $guruId = $this->getGuruId($request);
        if (!$guruId) return response()->json(['success' => false, 'message' => 'Data Guru tidak ditemukan.'], Response::HTTP_FORBIDDEN);

        $now = Carbon::now('Asia/Jakarta');
        $hariIni = $now->locale('id')->dayName;
        $taAktif = TahunAjaran::where('is_active', true)->first();

        if (!$taAktif) {
            return response()->json(['success' => false, 'message' => 'Tidak ada Tahun Ajaran aktif.'], Response::HTTP_NOT_FOUND);
        }

        $jadwal = GuruMapel::with(['mapel', 'kelas', 'jamMulai', 'jamSelesai'])
            ->where('guru_staf_id', $guruId)
            ->where('hari', $hariIni)
            ->where('tahun_ajaran_id', $taAktif->id)
            ->whereHas('mapel', fn($q) => $q->where('is_active', 1))
            ->whereHas('kelas', fn($q) => $q->where('is_active', 1))
            ->get();

        $sudahAbsen = PresensiGuruMapel::whereDate('tanggal', Carbon::today())
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
                'tanggal' => $now->toDateString(),
                'hari'    => $hariIni,
                'total_jadwal' => $data->count()
            ],
            'data' => $data
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
            $jadwal = GuruMapel::with(['mapel', 'kelas'])
                ->where('guru_staf_id', $guruId)
                ->where('tahun_ajaran_id', $taAktif?->id)
                ->findOrFail($guru_mapel_id);

            $targetDate = $request->get('tanggal', Carbon::today()->toDateString());

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

    public function showSiswa($id, Request $request): JsonResponse
    {
        $guruId = $this->getGuruId($request);
        $taAktif = TahunAjaran::where('is_active', true)->first();

        $kelasIds = GuruMapel::where('guru_staf_id', $guruId)
            ->where('tahun_ajaran_id', $taAktif?->id)
            ->pluck('kelas_id')
            ->unique();

        try {
            $siswa = Siswa::with('kelas')
                ->whereIn('kelas_id', $kelasIds)
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id'            => $siswa->id,
                    'nama_lengkap'  => $siswa->nama_lengkap,
                    'nisn'          => $siswa->nisn,
                    'nis'           => $siswa->nis,
                    'kelas'         => $siswa->kelas?->nama_kelas,
                    'jenis_kelamin' => $siswa->jenis_kelamin,
                    'is_active'     => $siswa->is_active
                ]
            ], Response::HTTP_OK);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Siswa tidak ditemukan atau bukan di kelas Anda.'], Response::HTTP_NOT_FOUND);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan sistem.'], Response::HTTP_INTERNAL_SERVER_ERROR);
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

        return response()->json([
            'success' => true,
            'message' => 'Riwayat mengajar tanggal ' . $tanggal,
            'data'    => PresensiGuruMapelResource::collection($query->get())
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
        $tanggal = $now->toDateString();
        $jamSekarang = $now->toTimeString();
        $hariIni = $now->locale('id')->dayName; 

        if ($this->isDayOff($tanggal)) {
            return response()->json(['success' => false, 'message' => 'Tidak dapat mengisi presensi pada hari libur.'], Response::HTTP_BAD_REQUEST);
        }

        $guruId = $this->getGuruId($request);
        $taAktif = TahunAjaran::where('is_active', true)->firstOrFail();

        try {
            $relasi = GuruMapel::with(['jamMulai', 'jamSelesai', 'kelas', 'mapel'])
                ->where('guru_staf_id', $guruId)
                ->where('tahun_ajaran_id', $taAktif->id)
                ->whereHas('mapel', fn($q) => $q->where('is_active', 1))
                ->findOrFail($request->input('guru_mapel_id'));

            if (strcasecmp(trim($relasi->hari), trim($hariIni)) !== 0) {
                return response()->json(['success' => false, 'message' => "Jadwal ini hari {$relasi->hari}, sekarang $hariIni."], Response::HTTP_FORBIDDEN);
            }

            if ($jamSekarang < $relasi->jamMulai?->waktu_mulai) {
                return response()->json(['success' => false, 'message' => "Belum waktunya mengisi presensi. Jadwal dimulai pukul " . $relasi->jamMulai?->waktu_mulai], Response::HTTP_FORBIDDEN);
            }

            if ($jamSekarang > $relasi->jamSelesai?->waktu_selesai) {
                return response()->json(['success' => false, 'message' => "Batas waktu pengisian telah berakhir. Jadwal selesai pukul " . $relasi->jamSelesai?->waktu_selesai], Response::HTTP_FORBIDDEN);
            }

            $validSiswaIds = Siswa::where('kelas_id', $relasi->kelas_id)
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            $inputPresensi = collect($request->input('presensi', []));
            $inputSiswaIds = $inputPresensi->pluck('siswa_id')->toArray();

            $invalidIds = array_diff($inputSiswaIds, $validSiswaIds);
            if (!empty($invalidIds) || count($inputSiswaIds) !== count($validSiswaIds)) {
                return response()->json(['success' => false, 'message' => 'Daftar siswa tidak valid atau tidak lengkap untuk kelas ini.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $presensi = DB::transaction(function () use ($inputPresensi, $relasi, $tanggal, $taAktif, $request) {
                $header = PresensiGuruMapel::updateOrCreate(
                    ['guru_mapel_id' => $relasi->id, 'tanggal' => $tanggal],
                    [
                        'kelas_id'          => $relasi->kelas_id,
                        'mata_pelajaran_id' => $relasi->mata_pelajaran_id,
                        'tahun_ajaran_id'   => $taAktif->id,
                        'jam_masuk'         => $relasi->jam_mulai_id, 
                        'jam_keluar'        => $relasi->jam_selesai_id,
                        'materi'            => $request->input('materi'),
                    ]
                );

                foreach ($inputPresensi as $dataSiswa) {
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

            return (new PresensiGuruMapelResource($presensi->load([
                'getBySiswaDetil.siswa', 
                'mapel', 
                'kelas', 
                'guruMapel.jamMulai', 
                'guruMapel.jamSelesai',
                'jamMasukDetail',
                'jamKeluarDetail'
            ])))->response()->setStatusCode(Response::HTTP_CREATED);

        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Jadwal tidak ditemukan atau akses dilarang.'], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Guru Store Presensi Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
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

            $bulan = (int) $request->get('bulan', date('m'));
            $tahun = $ta ? $this->determineYear($ta, $bulan) : date('Y');

            $query = PresensiGuruMapel::query()
                ->where('guru_mapel_id', $jadwal->id)
                ->whereMonth('tanggal', $bulan)
                ->whereYear('tanggal', $tahun);

            $namaKelas = $jadwal->kelas?->nama_kelas ?? 'Unknown';
            $namaMapel = $jadwal->mapel?->nama_mapel ?? 'Mapel';
            $user = $request->user();
            $namaGuru = $user->guruStaf?->nama ?? 'Guru';

            return Excel::download(
                new PresensiGuruMapelExport(
                    $query, 
                    "Bulan-{$bulan}-{$tahun}", 
                    DB::table('profil_sekolah')->first(), 
                    DB::table('data_kontak')->first(),
                    (object)['nama' => $namaGuru, 'nip' => $user->guruStaf?->nip ?? '-'],
                    $ta ? ($ta->nama . " (" . $ta->semester . ")") : '-',
                    true, 
                    $bulan,
                    $tahun
                ),
                "Rekap_Presensi_{$namaMapel}_{$namaKelas}_Bulan_{$bulan}.xlsx"
            );
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Jadwal tidak ditemukan.'], Response::HTTP_NOT_FOUND);
        }
    }
}