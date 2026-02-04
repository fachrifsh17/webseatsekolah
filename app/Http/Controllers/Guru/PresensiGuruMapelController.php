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
        $this->middleware('log.admin')->only(('store'));
    }

    private function getGuruId(Request $request)
    {
        $user = $request->user();
        $id = $user->guruStaf?->id;
        return $id ? (int)$id : null;
    }

    private function isDayOff($date): bool
    {
        $libur = DB::table('kalender_akademik')->where('kategori', 'Libur')
            ->whereDate('tanggal_mulai', '<=', $date)
            ->whereDate('tanggal_selesai', '>=', $date)->first();
        return $libur || date('N', strtotime($date)) >= 6;
    }

    public function jadwalHariIni(Request $request): JsonResponse
    {
        $guruId = $this->getGuruId($request);
        $hariIni = Carbon::now('Asia/Jakarta')->locale('id')->dayName;

        $jadwal = GuruMapel::with(['mataPelajaran', 'kelas', 'jamMulai', 'jamSelesai'])
            ->where('guru_staf_id', $guruId)
            ->where('hari', $hariIni)
            ->get();

        return response()->json([
            'success' => true,
            'message' => "Daftar jadwal mengajar hari $hariIni",
            'data' => $jadwal
        ], Response::HTTP_OK);
    }

    public function index(Request $request): JsonResponse
    {
        $guruId = $this->getGuruId($request);
        $tanggal = $request->get('tanggal', date('Y-m-d'));

        $query = PresensiGuruMapel::with(['mataPelajaran', 'kelas', 'presensiSiswaDetail.siswa'])
            ->whereHas('guruMapel', function ($q) use ($guruId) {
                $q->where('guru_staf_id', $guruId);
            })
            ->whereDate('tanggal', $tanggal)
            ->latest();

        return response()->json([
            'success' => true,
            'message' => 'Riwayat mengajar tanggal ' . $tanggal,
            'data' => PresensiGuruMapelResource::collection($query->get())
        ], Response::HTTP_OK);
    }

    public function show($id, Request $request): JsonResponse
    {
        $guruId = $this->getGuruId($request);
        $jadwal = GuruMapel::with(['mataPelajaran', 'kelas', 'jamMulai', 'jamSelesai'])->findOrFail($id);

        if ($jadwal->guru_staf_id !== $guruId) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak.'
            ], Response::HTTP_FORBIDDEN);
        }

        $siswa = Siswa::where('kelas_id', $jadwal->kelas_id)
            ->where('is_active', true)
            ->select('id', 'nama_lengkap', 'nisn')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'jadwal' => $jadwal,
                'siswa' => $siswa
            ]
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

        $relasi = GuruMapel::with(['jamMulai', 'jamSelesai', 'kelas'])->findOrFail($request->input('guru_mapel_id'));
        $guruId = $this->getGuruId($request);

        if ($relasi->guru_staf_id !== $guruId) {
            return response()->json([
                'success' => false, 
                'message' => 'Akses ditolak.'
            ], Response::HTTP_FORBIDDEN);
        }

        if (strcasecmp(trim($relasi->hari), trim($hariIni)) !== 0) {
            return response()->json([
                'success' => false, 
                'message' => "Jadwal ini hari {$relasi->hari}, sekarang $hariIni."
            ], Response::HTTP_FORBIDDEN);
        }

        $waktuMulaiJadwal = $relasi->jamMulai?->waktu_mulai;
        $waktuSelesaiJadwal = $relasi->jamSelesai?->waktu_selesai;

        if ($jamSekarang < $waktuMulaiJadwal) {
            return response()->json([
                'success' => false, 
                'message' => "Belum waktunya mengisi presensi. Jadwal dimulai jam $waktuMulaiJadwal."
            ], Response::HTTP_FORBIDDEN);
        }

        if ($jamSekarang > $waktuSelesaiJadwal) {
            return response()->json([
                'success' => false, 
                'message' => "Batas waktu pengisian telah berakhir pada jam $waktuSelesaiJadwal."
            ], Response::HTTP_FORBIDDEN);
        }

        $inputPresensi = collect($request->input('presensi', []));
        $semuaSiswaSahIds = Siswa::where('kelas_id', $relasi->kelas_id)
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();

        try {
            $presensi = DB::transaction(function () use ($request, $relasi, $semuaSiswaSahIds, $inputPresensi, $tanggal) {
                $header = PresensiGuruMapel::updateOrCreate(
                    ['guru_mapel_id' => $relasi->id, 'tanggal' => $tanggal],
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
                            'status' => $dataSiswa['status'] ?? 'hadir',
                            'catatan' => $dataSiswa['catatan'] ?? null
                        ]
                    );
                }
                return $header->load('presensiSiswaDetail.siswa', 'mataPelajaran', 'kelas');
            });

            return (new PresensiGuruMapelResource($presensi))->response()->setStatusCode(Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Gagal menyimpan data.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        $guruId = $this->getGuruId($request);
        $bulan = $request->query('bulan', date('m'));
        $tahun = $request->query('tahun', date('Y'));
        $kelasId = $request->query('kelas_id'); 
        
        if (!$kelasId) {
            return response()->json(['success' => false, 'message' => 'Pilih kelas terlebih dahulu.'], 422);
        }

        $query = PresensiGuruMapel::with(['mataPelajaran', 'kelas', 'presensiSiswaDetail.siswa'])
            ->whereHas('guruMapel', function ($q) use ($guruId) {
                $q->where('guru_staf_id', $guruId);
            })
            ->whereMonth('tanggal', $bulan)
            ->whereYear('tanggal', $tahun)
            ->where('kelas_id', $kelasId);

        $namaKelas = DB::table('kelas')->where('id', $kelasId)->value('nama_kelas') ?? $kelasId;
        $taAktif = DB::table('tahun_ajaran')->where('is_active', 1)->first();
        $tahunAjaranId = $request->query('tahun_ajaran_id') ?? $taAktif?->id;
        $taData = DB::table('tahun_ajaran')->where('id', $tahunAjaranId)->first();

        $user = $request->user();
        $namaGuru = $user->guruStaf?->nama ?? 'Guru';

        return Excel::download(
            new PresensiGuruMapelExport(
                $query, 
                "Bulan-{$bulan}-{$tahun}", 
                DB::table('profil_sekolah')->first(), 
                DB::table('data_kontak')->first(),
                (object)['nama' => $namaGuru, 'nip' => $user->guruStaf?->nip ?? '-'],
                $taData ? $taData->nama . " (" . $taData->semester . ")" : '-',
                true, 
                $bulan,
                $tahun
            ),
            "Rekap_Presensi_{$namaGuru}_Kelas_{$namaKelas}_Bulan_{$bulan}_{$tahun}.xlsx"
        );
    }
}