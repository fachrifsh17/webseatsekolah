<?php

namespace App\Http\Controllers\KepalaSekolah;

use App\Http\Controllers\Controller;
use App\Models\PresensiGuruMapel;
use App\Models\GuruMapel;
use App\Models\TahunAjaran;
use App\Http\Resources\PresensiGuruMapelResource;
use App\Exports\PresensiGuruMapelExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;
use Throwable;

class PresensiGuruMapelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = PresensiGuruMapel::with(['presensiSiswaDetail.siswa', 'guruMapel.guru', 'mataPelajaran', 'kelas']);

            if (!$request->has('show_all')) {
                $query->whereHas('mataPelajaran', function ($q) {
                    $q->where('is_active', 1);
                });
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
            ->whereHas('mapel', function ($q) {
                $q->where('is_active', 1);
            })
            ->get();

        $sudahAbsen = PresensiGuruMapel::whereDate('tanggal', Carbon::today())
            ->pluck('guru_mapel_id')
            ->toArray();

        $data = $jadwal->map(function($j) use ($sudahAbsen) {
            $mulai = $j->jamMulai?->jam_ke;
            $selesai = $j->jamSelesai?->jam_ke;
            
            return [
                'id_jadwal' => $j->id,
                'nama_guru' => $j->guru?->nama,
                'mapel' => $j->mapel?->nama_mapel,
                'kelas' => $j->kelas?->nama_kelas,
                'jam' => ($mulai && $selesai) ? "Jam Ke $mulai - $selesai" : "-",
                'status' => in_array($j->id, $sudahAbsen) ? 'Sudah Jurnal' : 'Belum Jurnal'
            ];
        });

        return response()->json(['success' => true, 'data' => $data], Response::HTTP_OK);
    }

    public function export(Request $request)
    {
        try {
            $month = $request->query('month', date('m'));
            $year = $request->query('year', date('Y'));
            $guruStafId = $request->query('guru_staf_id');
            $taAktif = TahunAjaran::where('is_active', 1)->first();
            
            $query = PresensiGuruMapel::whereMonth('tanggal', $month)->whereYear('tanggal', $year);
           
            if (!$request->has('include_inactive')) {
                $query->whereHas('mataPelajaran', fn($q) => $q->where('is_active', 1));
            }

            if ($guruStafId) {
                $query->whereHas('guruMapel', fn($q) => $q->where('guru_staf_id', $guruStafId));
            }

            $guruTarget = $guruStafId ? DB::table('guru_staf')->where('id', $guruStafId)->first() : null;
            $namaGuru = $guruTarget ? str_replace(' ', '_', $guruTarget->nama) : 'Semua_Guru';

            return Excel::download(
                new PresensiGuruMapelExport(
                    $query, 
                    "Bulan-$month-$year", 
                    DB::table('profil_sekolah')->first(), 
                    DB::table('data_kontak')->first(), 
                    (object)['nama' => $guruTarget->nama ?? 'Semua', 'nip' => $guruTarget->nip ?? '-'], 
                    $taAktif ? $taAktif->nama : '-', 
                    $request->filled('kelas_id')
                ),
                "Jurnal_Guru_{$namaGuru}_Bulan_{$month}_{$year}.xlsx"
            );
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal mengekspor data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}