<?php

namespace App\Http\Controllers\Admin;

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
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

class PresensiGuruMapelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.admin');
    }

    public function index(Request $request): JsonResponse
    {
        $query = PresensiGuruMapel::with([
            'presensiSiswaDetail.siswa', 
            'guruMapel.guru', 
            'mataPelajaran', 
            'kelas'
        ]);

        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        }

        if ($request->filled('guru_staf_id')) {
            $query->whereHas('guruMapel', fn($q) => $q->where('guru_staf_id', $request->guru_staf_id));
        }

        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', $request->kelas_id);
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $paginated = $query->latest('tanggal')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => PresensiGuruMapelResource::collection($paginated),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ]
        ], Response::HTTP_OK);
    }

    public function listJadwalHariIni(): JsonResponse
    {
        $hariIni = Carbon::now()->locale('id')->dayName;

        $jadwal = GuruMapel::with(['mapel', 'kelas', 'guru'])
            ->where('hari', $hariIni)
            ->get();

        $sudahAbsen = PresensiGuruMapel::whereDate('tanggal', Carbon::today())
            ->pluck('guru_mapel_id')
            ->toArray();

        $data = $jadwal->map(function ($j) use ($sudahAbsen) {
            return [
                'guru_mapel_id' => $j->id,
                'nama_guru' => $j->guru?->nama,
                'mata_pelajaran' => $j->mapel?->nama_mapel,
                'kelas' => $j->kelas?->nama_kelas,
                'jam' => $j->jam_mulai_id . ' - ' . $j->jam_selesai_id,
                'status' => in_array($j->id, $sudahAbsen) ? 'Sudah Absen' : 'Belum Absen'
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ], Response::HTTP_OK);
    }

    public function store(StorePresensiGuruMapelRequest $request): JsonResponse
    {
        $relasi = GuruMapel::findOrFail($request->input('guru_mapel_id'));

        try {
            $presensi = DB::transaction(function () use ($request, $relasi) {
                $header = PresensiGuruMapel::updateOrCreate(
                    [
                        'guru_mapel_id' => $relasi->id,
                        'tanggal' => Carbon::today()->toDateString()
                    ],
                    [
                        'kelas_id' => $relasi->kelas_id,
                        'mata_pelajaran_id' => $relasi->mata_pelajaran_id,
                        'jam_masuk' => $relasi->jam_mulai_id,
                        'jam_keluar' => $relasi->jam_selesai_id,
                        'materi' => $request->input('materi'),
                    ]
                );

                $inputPresensi = collect($request->input('presensi', []));
                $semuaSiswaIds = Siswa::where('kelas_id', $relasi->kelas_id)->pluck('id');

                foreach ($semuaSiswaIds as $siswaId) {
                    $dataSiswa = $inputPresensi->firstWhere('siswa_id', $siswaId);
                    $header->presensiSiswaDetail()->updateOrCreate(
                        ['siswa_id' => $siswaId],
                        [
                            'status' => $dataSiswa['status'] ?? 'alfa',
                            'catatan' => $dataSiswa['catatan'] ?? null
                        ]
                    );
                }
                return $header->load(['presensiSiswaDetail.siswa', 'guruMapel.guru', 'mataPelajaran', 'kelas']);
            });

            return (new PresensiGuruMapelResource($presensi))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $presensi = PresensiGuruMapel::findOrFail($id);

        try {
            $updated = DB::transaction(function () use ($request, $presensi) {
                $presensi->update([
                    'materi' => $request->input('materi', $presensi->materi),
                ]);

                if ($request->has('presensi')) {
                    foreach ($request->input('presensi') as $item) {
                        $presensi->presensiSiswaDetail()
                            ->where('siswa_id', $item['siswa_id'])
                            ->update([
                                'status' => $item['status'],
                                'catatan' => $item['catatan'] ?? null
                            ]);
                    }
                }

                return $presensi->load(['presensiSiswaDetail.siswa', 'guruMapel.guru', 'mataPelajaran', 'kelas']);
            });

            return response()->json([
                'success' => true,
                'data' => new PresensiGuruMapelResource($updated)
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $presensi = PresensiGuruMapel::findOrFail($id);
        $presensi->delete();

        return response()->json([
            'success' => true, 
            'message' => 'Berhasil dihapus.'
        ], Response::HTTP_OK);
    }

    public function export(Request $request)
    {
        $month = $request->query('month', date('m'));
        $year = $request->query('year', date('Y'));
        $kelasId = $request->query('kelas_id');
        $guruStafId = $request->query('guru_staf_id');
        $semester = $request->query('semester');

        $taAktif = DB::table('tahun_ajaran')->where('is_active', 1)->first();
        $tahunAjaranId = $request->query('tahun_ajaran_id') ?? $taAktif?->id;

        $labelWaktu = "Bulan-{$month}-{$year}";

        $query = PresensiGuruMapel::query()
            ->whereMonth('tanggal', $month)
            ->whereYear('tanggal', $year);

        $query->whereHas('guruMapel', function ($q) use ($tahunAjaranId, $semester) {
            if ($tahunAjaranId) {
                $q->where('tahun_ajaran_id', $tahunAjaranId);
            }
            if ($semester) {
                $q->whereHas('tahunAjaran', fn($sq) => $sq->where('semester', $semester));
            }
        });

        if ($kelasId) {
            $query->where('kelas_id', $kelasId);
        }

        if ($guruStafId) {
            $query->whereHas('guruMapel', fn($q) => $q->where('guru_staf_id', $guruStafId));
        }

        $profil = DB::table('profil_sekolah')->first();
        $kontak = DB::table('data_kontak')->first();

        $taData = DB::table('tahun_ajaran')->where('id', $tahunAjaranId)->first();
        $tahunAjaranLabel = $taData ? $taData->nama . " (" . $taData->semester . ")" : '-';

        $guruTarget = DB::table('guru_staf')->where('id', $guruStafId)->first();
        $labelGuru = $guruTarget ? str_replace(' ', '_', $guruTarget->nama) : 'Semua_Guru';

        $labelKelas = '';
        if ($kelasId) {
            $namaKelas = DB::table('kelas')->where('id', $kelasId)->value('nama_kelas');
            $labelKelas = $namaKelas ? '_' . str_replace(' ', '_', $namaKelas) : '';
        }

        $guruData = (object)[
            'nama' => $guruTarget->nama ?? 'Semua Guru',
            'nip' => $guruTarget->nip ?? '-'
        ];

        $isFilterKelas = $request->filled('kelas_id');
        $fileName = "Rekap_Jurnal_{$labelGuru}{$labelKelas}_{$month}_{$year}.xlsx";

        return Excel::download(
            new PresensiGuruMapelExport(
                $query,
                $labelWaktu,
                $profil,
                $kontak,
                $guruData,
                $tahunAjaranLabel,
                $isFilterKelas
            ),
            $fileName
        );
    }
}