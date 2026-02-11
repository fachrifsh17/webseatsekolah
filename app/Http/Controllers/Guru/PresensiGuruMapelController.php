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
use Carbon\Carbon;

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
        $id = $user->guruStaf?->id;
        return $id ? (int)$id : null;
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

    public function jadwalHariIni(Request $request): JsonResponse
    {
        $guruId = $this->getGuruId($request);
        $hariIni = Carbon::now('Asia/Jakarta')->locale('id')->dayName;

        $jadwal = GuruMapel::with(['mapel', 'kelas', 'jamMulai', 'jamSelesai', 'guru'])
            ->where('guru_staf_id', $guruId)
            ->where('hari', $hariIni)
            ->whereHas('mapel', function ($query) {
                $query->where('is_active', 1);
            })
            ->get();

        $sudahAbsen = PresensiGuruMapel::whereDate('tanggal', Carbon::today())
            ->whereHas('guruMapel', function ($q) use ($guruId) {
                $q->where('guru_staf_id', $guruId);
            })
            ->pluck('guru_mapel_id')
            ->toArray();

        $dataTransformed = $jadwal->map(function ($item) use ($sudahAbsen) {
            $mulai = $item->jamMulai?->jam_ke;
            $selesai = $item->jamSelesai?->jam_ke;

            return [
                'guru_mapel_id'  => $item->id,
                'nama_guru'      => $item->guru?->nama,
                'mata_pelajaran' => $item->mapel?->nama_mapel,
                'kelas'          => $item->kelas?->nama_kelas,
                'jam'            => ($mulai && $selesai) ? "Jam Ke $mulai - $selesai" : "-",
                'status'         => in_array($item->id, $sudahAbsen) ? 'Sudah Absen' : 'Belum Absen'
            ];
        });

        return response()->json([
            'success' => true,
            'message' => "Daftar jadwal mengajar hari $hariIni",
            'data'    => $dataTransformed
        ], Response::HTTP_OK);
    }

    public function getSiswaByJadwal($guru_mapel_id, Request $request): JsonResponse
    {
        $guruId = $this->getGuruId($request);
        
        $jadwal = GuruMapel::with(['mapel', 'kelas', 'jamMulai', 'jamSelesai'])
            ->where('guru_staf_id', $guruId)
            ->whereHas('mapel', function ($q) {
                $q->where('is_active', 1);
            })
            ->findOrFail($guru_mapel_id);

        $siswa = Siswa::where('kelas_id', $jadwal->kelas_id)
            ->where('is_active', true)
            ->select('id', 'nama_lengkap', 'nisn')
            ->orderBy('nama_lengkap', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'info' => [
                'guru_mapel_id'  => $jadwal->id,
                'mata_pelajaran' => $jadwal->mapel?->nama_mapel,
                'kelas'          => $jadwal->kelas?->nama_kelas,
                'tanggal'        => Carbon::today()->toDateString(),
            ],
            'data' => $siswa->map(fn($s) => [
                'siswa_id' => $s->id,
                'nama'     => $s->nama_lengkap,
                'nisn'     => $s->nisn,
                'status'   => 'hadir',
                'catatan'  => null
            ])
        ], Response::HTTP_OK);
    }

    public function index(Request $request): JsonResponse
    {
        $guruId = $this->getGuruId($request);
        $tanggal = $request->get('tanggal', date('Y-m-d'));

        $query = PresensiGuruMapel::with([
            'mapel', 
            'kelas', 
            'getBySiswaDetil.siswa', 
            'guruMapel.jamMulai', 
            'guruMapel.jamSelesai',
            'jamMasukDetail',
            'jamKeluarDetail'
        ])
        ->whereHas('guruMapel', function ($q) use ($guruId) {
            $q->where('guru_staf_id', $guruId);
        })
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
        
        $presensi = PresensiGuruMapel::with([
            'mapel', 
            'kelas', 
            'getBySiswaDetil.siswa', 
            'guruMapel.jamMulai', 
            'guruMapel.jamSelesai',
            'jamMasukDetail',
            'jamKeluarDetail'
        ])
        ->whereHas('guruMapel', function ($q) use ($guruId) {
            $q->where('guru_staf_id', $guruId);
        })
        ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => new PresensiGuruMapelResource($presensi)
        ], Response::HTTP_OK);
    }

    public function store(StorePresensiGuruMapelRequest $request): JsonResponse
    {
        $now = Carbon::now('Asia/Jakarta');
        $tanggal = $now->toDateString();
        $jamSekarang = $now->toTimeString();
        $hariIni = $now->locale('id')->dayName; 

        if ($this->isDayOff($tanggal)) {
            return response()->json([
                'success' => false, 
                'message' => 'Tidak dapat mengisi presensi pada hari libur.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $guruId = $this->getGuruId($request);
        $relasi = GuruMapel::with(['jamMulai', 'jamSelesai', 'kelas', 'mapel'])
            ->where('guru_staf_id', $guruId)
            ->whereHas('mapel', fn($q) => $q->where('is_active', 1))
            ->findOrFail($request->input('guru_mapel_id'));

        if (strcasecmp(trim($relasi->hari), trim($hariIni)) !== 0) {
            return response()->json([
                'success' => false, 
                'message' => "Jadwal ini hari {$relasi->hari}, sekarang $hariIni."
            ], Response::HTTP_FORBIDDEN);
        }

        if ($jamSekarang < $relasi->jamMulai?->waktu_mulai) {
            return response()->json([
                'success' => false, 
                'message' => "Belum waktunya mengisi presensi."
            ], Response::HTTP_FORBIDDEN);
        }

        $taAktif = TahunAjaran::where('is_active', true)->first();

        try {
            $presensi = DB::transaction(function () use ($request, $relasi, $tanggal, $taAktif) {
                $header = PresensiGuruMapel::updateOrCreate(
                    ['guru_mapel_id' => $relasi->id, 'tanggal' => $tanggal],
                    [
                        'kelas_id'          => $relasi->kelas_id,
                        'mata_pelajaran_id' => $relasi->mata_pelajaran_id,
                        'tahun_ajaran_id'   => $taAktif->id ?? $relasi->tahun_ajaran_id,
                        'jam_masuk'         => $relasi->jam_mulai_id, 
                        'jam_keluar'        => $relasi->jam_selesai_id,
                        'materi'            => $request->input('materi'),
                    ]
                );

                $inputPresensi = collect($request->input('presensi', []));
                $siswaIds = Siswa::where('kelas_id', $relasi->kelas_id)->where('is_active', true)->pluck('id');

                foreach ($siswaIds as $siswaId) {
                    $dataSiswa = $inputPresensi->firstWhere('siswa_id', $siswaId);
                    $header->getBySiswaDetil()->updateOrCreate(
                        ['siswa_id' => $siswaId],
                        [
                            'status'  => $dataSiswa['status'] ?? 'hadir',
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

        } catch (\Exception $e) {
            Log::error('Guru Store Presensi Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal menyimpan data.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        $guruId = $this->getGuruId($request);
        
        if (!$request->filled('kelas_id')) {
            return response()->json([
                'success' => false, 
                'message' => 'Pilih kelas terlebih dahulu.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $ta = $request->filled('tahun_ajaran_id') 
            ? TahunAjaran::find($request->tahun_ajaran_id) 
            : TahunAjaran::where('is_active', true)->first();

        $bulan = (int) $request->get('bulan', date('m'));
        $tahun = $ta ? $this->determineYear($ta, $bulan) : date('Y');

        $query = PresensiGuruMapel::query()
            ->whereHas('guruMapel', function ($q) use ($guruId) {
                $q->where('guru_staf_id', $guruId);
            })
            ->whereMonth('tanggal', $bulan)
            ->whereYear('tanggal', $tahun)
            ->where('kelas_id', $request->kelas_id);

        $namaKelas = Kelas::where('id', $request->kelas_id)->value('nama_kelas') ?? 'Unknown';
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
            "Rekap_Presensi_{$namaGuru}_{$namaKelas}_Bulan_{$bulan}.xlsx"
        );
    }
}