<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\PresensiGuruMapel;
use App\Models\GuruMapel;
use App\Models\Siswa;
use App\Http\Requests\StorePresensiGuruMapelRequest;
use App\Http\Resources\PresensiGuruMapelResource;
use App\Exports\PresensiGuruMapelExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

class PresensiGuruMapelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
    }

    private function getGuruId(Request $request)
    {
        return $request->user()->guru?->id ?? $request->user()->guruStaf?->id;
    }

    private function isDayOff($date): bool
    {
        $libur = DB::table('kalender_akademik')->where('kategori', 'Libur')
            ->whereDate('tanggal_mulai', '<=', $date)
            ->whereDate('tanggal_selesai', '>=', $date)->first();
            
        return $libur || date('N', strtotime($date)) >= 6;
    }

    public function index(Request $request): JsonResponse
    {
        $guruId = $this->getGuruId($request);
        $query = PresensiGuruMapel::with(['mataPelajaran', 'kelas', 'presensiSiswaDetail']);

        $query->whereHas('guruMapel', function ($q) use ($guruId) {
            $q->where('guru_staf_id', $guruId);
        });

        $history = $query->latest('tanggal')->get();

        return response()->json([
            'success' => true,
            'data' => PresensiGuruMapelResource::collection($history)
        ], Response::HTTP_OK);
    }

    public function tugasHariIni(Request $request): JsonResponse
    {
        $now = Carbon::now('Asia/Jakarta');
        $hariIni = $now->locale('id')->dayName; 
        $guruId = $this->getGuruId($request);
        $waktuSekarang = $now->format('H:i:s');
        $tanggal = $now->toDateString();

        if ($this->isDayOff($tanggal)) {
            return response()->json([
                'success' => true,
                'hari' => $hariIni,
                'tanggal' => $tanggal,
                'message' => 'Hari ini adalah hari libur.',
                'data' => []
            ], Response::HTTP_OK);
        }

        $tugasList = GuruMapel::with(['mataPelajaran', 'kelas', 'jamMulai', 'jamSelesai'])
            ->where('hari', $hariIni)
            ->where('guru_staf_id', $guruId)
            ->whereHas('tahunAjaran', fn($q) => $q->where('is_active', 1))
            ->get();

        $sudahPresensiIds = PresensiGuruMapel::whereIn('guru_mapel_id', $tugasList->pluck('id'))
            ->whereDate('tanggal', $tanggal)
            ->pluck('guru_mapel_id')
            ->toArray();

        $data = $tugasList->map(function ($tugas) use ($sudahPresensiIds, $waktuSekarang) {
            $jamMulai = $tugas->jamMulai?->waktu_mulai;
            $jamSelesai = $tugas->jamSelesai?->waktu_selesai;
            $is_open = ($waktuSekarang >= $jamMulai && $waktuSekarang <= $jamSelesai);

            return [
                'id' => $tugas->id,
                'mata_pelajaran' => $tugas->mataPelajaran?->nama_mapel,
                'kelas' => $tugas->kelas?->nama_kelas,
                'rentang_waktu' => $jamMulai . ' - ' . $jamSelesai,
                'is_open' => $is_open,
                'status_presensi' => in_array($tugas->id, $sudahPresensiIds) ? 'Sudah Diisi' : 'Belum Diisi'
            ];
        });

        return response()->json([
            'success' => true,
            'hari' => $hariIni,
            'tanggal' => $tanggal,
            'data' => $data
        ], Response::HTTP_OK);
    }

    public function store(StorePresensiGuruMapelRequest $request): JsonResponse
    {
        $now = Carbon::now('Asia/Jakarta');
        $tanggal = $now->toDateString();

        if ($this->isDayOff($tanggal)) {
            return response()->json(['success' => false, 'message' => 'Tidak dapat mengisi presensi pada hari libur.'], Response::HTTP_BAD_REQUEST);
        }

        $relasi = GuruMapel::with(['jamMulai', 'jamSelesai'])->findOrFail($request->input('guru_mapel_id'));
        $guruId = $this->getGuruId($request);
        
        $hariIni = $now->locale('id')->dayName; 
        $waktuSekarang = $now->format('H:i:s');

        if ($relasi->guru_staf_id != $guruId) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak. Ini bukan jadwal Anda.'], Response::HTTP_FORBIDDEN);
        }

        if (strtolower($relasi->hari) !== strtolower($hariIni)) {
            return response()->json([
                'success' => false,
                'message' => "Akses ditolak. Jadwal ini untuk hari {$relasi->hari}, sekarang hari $hariIni."
            ], Response::HTTP_FORBIDDEN);
        }

        $batasWaktuMulai = $relasi->jamMulai?->waktu_mulai;
        if ($now->lt(Carbon::createFromTimeString($batasWaktuMulai, 'Asia/Jakarta'))) {
            return response()->json([
                'success' => false,
                'message' => "Akses ditolak. Pelajaran belum dimulai. Sekarang pukul $waktuSekarang"
            ], Response::HTTP_FORBIDDEN);
        }

        $inputPresensi = collect($request->input('presensi', []));
        $semuaSiswaSahIds = Siswa::where('kelas_id', $relasi->kelas_id)->pluck('id')->toArray();

        try {
            $presensi = DB::transaction(function () use ($request, $relasi, $semuaSiswaSahIds, $inputPresensi, $tanggal) {
                $header = PresensiGuruMapel::updateOrCreate(
                    [
                        'guru_mapel_id' => $relasi->id, 
                        'tanggal' => $tanggal
                    ],
                    [
                        'kelas_id' => $relasi->kelas_id,
                        'mata_pelajaran_id' => $relasi->mata_pelajaran_id,
                        'jam_masuk' => $relasi->jam_mulai_id, 
                        'jam_keluar' => $relasi->jam_selesai_id,
                        'materi' => $request->input('materi'),
                    ]
                );

                foreach ($semuaSiswaSahIds as $siswaId) {
                    $dataSiswa = $inputPresensi->firstWhere('siswa_id', $siswaId);
                    $header->presensiSiswaDetail()->updateOrCreate(
                        ['siswa_id' => $siswaId],
                        [
                            'status' => $dataSiswa['status'] ?? 'alfa', 
                            'catatan' => $dataSiswa['catatan'] ?? null
                        ]
                    );
                }
                return $header->load('presensiSiswaDetail.siswa', 'mataPelajaran', 'kelas');
            });

            return (new PresensiGuruMapelResource($presensi))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        $guruId = $this->getGuruId($request);
        $guruStaf = $request->user()->guruStaf ?? $request->user()->guru;

        $month = $request->query('month', Carbon::now()->month);
        $year = $request->query('year', Carbon::now()->year);
        $semester = $request->query('semester');

        $taAktif = DB::table('tahun_ajaran')->where('is_active', 1)->first();
        $tahunAjaranId = $request->query('tahun_ajaran_id') ?? $taAktif?->id;
        
        $labelWaktu = "Bulan-{$month}-{$year}";

        $query = PresensiGuruMapel::query()
            ->whereHas('guruMapel', function ($q) use ($guruId, $tahunAjaranId, $semester) {
                $q->where('guru_staf_id', $guruId);
                
                if ($tahunAjaranId) {
                    $q->where('tahun_ajaran_id', $tahunAjaranId);
                }

                if ($semester) {
                    $q->whereHas('tahunAjaran', fn($sq) => $sq->where('semester', $semester));
                }
            })
            ->whereMonth('tanggal', $month)
            ->whereYear('tanggal', $year)
            ->latest('tanggal');

        $profil = DB::table('profil_sekolah')->first(); 
        $kontak = DB::table('data_kontak')->first();
        
        $taData = DB::table('tahun_ajaran')->where('id', $tahunAjaranId)->first();
        $tahunAjaranLabel = $taData ? $taData->nama . " (" . $taData->semester . ")" : '-';

        $fileName = "Rekap_Mengajar_" . str_replace(' ', '_', $guruStaf->nama ?? 'Guru') . "_{$month}_{$year}.xlsx";

        return Excel::download(
            new PresensiGuruMapelExport(
                $query, 
                $labelWaktu, 
                $profil, 
                $kontak, 
                $guruStaf,
                $tahunAjaranLabel
            ), 
            $fileName
        );
    }
}