<?php

namespace App\Http\Controllers\KepalaSekolah;

use App\Http\Controllers\Controller;
use App\Models\{PresensiGuruMapel, GuruMapel, TahunAjaran, Kelas};
use App\Http\Resources\PresensiGuruMapelResource;
use App\Exports\PresensiGuruMapelExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log};
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;
use Throwable;

class PresensiGuruMapelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
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
        try {
            $query = PresensiGuruMapel::with(['presensiSiswaDetail.siswa', 'guruMapel.guru', 'mataPelajaran', 'kelas']);

            if (!$request->has('show_all')) {
                $query->whereHas('mataPelajaran', fn($q) => $q->where('is_active', 1));
            }

            if ($request->filled('tanggal')) {
                $query->whereDate('tanggal', $request->tanggal);
            }
            
            if ($request->filled('kelas_id')) {
                $query->where('kelas_id', $request->kelas_id);
            }
            
            if ($request->filled('guru_staf_id')) {
                $query->whereHas('guruMapel', fn($q) => $q->where('guru_staf_id', $request->guru_staf_id));
            }

            if ($request->filled('bulan') && $request->filled('tahun')) {
                $query->whereMonth('tanggal', $request->bulan)
                      ->whereYear('tanggal', $request->tahun);
            }

            $perPage = min((int) $request->get('per_page', 20), 100);
            $data = $query->latest('tanggal')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => PresensiGuruMapelResource::collection($data),
                'meta' => [
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                    'total' => $data->total(),
                ]
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal mengambil data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function listJadwalHariIni(): JsonResponse
    {
        $hariIni = Carbon::now('Asia/Jakarta')->locale('id')->dayName;
        $tahunAktif = TahunAjaran::where('is_active', 1)->first();
        
        $jadwal = GuruMapel::with(['mapel', 'kelas', 'guru', 'jamMulai', 'jamSelesai'])
            ->where('hari', $hariIni)
            ->where('tahun_ajaran_id', optional($tahunAktif)->id)
            ->whereHas('mapel', fn($q) => $q->where('is_active', 1))
            ->get();

        $sudahAbsen = PresensiGuruMapel::whereDate('tanggal', Carbon::today())
            ->pluck('guru_mapel_id')
            ->toArray();

        $data = $jadwal->map(function($j) use ($sudahAbsen) {
            return [
                'id_jadwal' => $j->id,
                'nama_guru' => $j->guru?->nama,
                'mapel' => $j->mapel?->nama_mapel,
                'kelas' => $j->kelas?->nama_kelas,
                'jam' => ($j->jamMulai && $j->jamSelesai) ? "Jam Ke {$j->jamMulai->jam_ke} - {$j->jamSelesai->jam_ke}" : "-",
                'status' => in_array($j->id, $sudahAbsen) ? 'Sudah Jurnal' : 'Belum Jurnal'
            ];
        });

        return response()->json(['success' => true, 'data' => $data], Response::HTTP_OK);
    }

    public function export(Request $request)
    {
        try {
            $ta = $request->filled('tahun_ajaran_id') 
                ? TahunAjaran::find($request->tahun_ajaran_id) 
                : TahunAjaran::where('is_active', 1)->first();

            $bulan = (int) $request->query('month', date('m'));
            $tahun = $this->determineYear($ta, $bulan);

            $query = PresensiGuruMapel::query()
                ->whereMonth('tanggal', $bulan)
                ->whereYear('tanggal', $tahun);

            if (!$request->has('include_inactive')) {
                $query->whereHas('mataPelajaran', fn($q) => $q->where('is_active', 1));
            }

            if ($request->filled('guru_staf_id')) {
                $query->whereHas('guruMapel', fn($q) => $q->where('guru_staf_id', $request->guru_staf_id));
            }

            if ($request->filled('kelas_id')) {
                $query->where('kelas_id', $request->kelas_id);
            }

            $guruTarget = $request->filled('guru_staf_id') 
                ? DB::table('guru_staf')->where('id', $request->guru_staf_id)->first() 
                : null;
            
            $namaGuru = $guruTarget ? str_replace(' ', '_', $guruTarget->nama) : 'Semua_Guru';
            $namaKelas = $request->filled('kelas_id') 
                ? Kelas::where('id', $request->kelas_id)->value('nama_kelas') 
                : 'Semua_Kelas';

            return Excel::download(
                new PresensiGuruMapelExport(
                    $query, 
                    "Bulan-$bulan-$tahun", 
                    DB::table('profil_sekolah')->first(), 
                    DB::table('data_kontak')->first(), 
                    (object)['nama' => $guruTarget->nama ?? 'Semua Guru', 'nip' => $guruTarget->nip ?? '-'], 
                    $ta ? "{$ta->nama} ({$ta->semester})" : '-', 
                    $request->filled('kelas_id'),
                    $bulan,
                    $tahun
                ),
                "Jurnal_{$namaGuru}_{$namaKelas}_Bulan_{$bulan}_{$tahun}.xlsx"
            );
        } catch (Throwable $e) {
            Log::error("Export Kepsek Error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengekspor data.'], 500);
        }
    }
}