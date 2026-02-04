<?php

namespace App\Http\Controllers\Kesiswaan;

use App\Http\Controllers\Controller;
use App\Models\PresensiGuruMapel;
use App\Models\GuruMapel;
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
        $this->middleware('log.admin')->only(['update']);
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

        $perHalaman = min((int) $request->get('per_page', 20), 100);
        $paginasi = $query->latest('tanggal')->paginate($perHalaman);

        return response()->json([
            'success' => true,
            'data' => PresensiGuruMapelResource::collection($paginasi),
            'meta' => [
                'current_page' => $paginasi->currentPage(),
                'last_page' => $paginasi->lastPage(),
                'per_page' => $paginasi->perPage(),
                'total' => $paginasi->total(),
            ]
        ], Response::HTTP_OK);
    }

    public function show($id): JsonResponse
    {
        $presensi = PresensiGuruMapel::with([
            'presensiSiswaDetail.siswa',
            'guruMapel.guru',
            'mataPelajaran',
            'kelas'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new PresensiGuruMapelResource($presensi)
        ], Response::HTTP_OK);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $presensi = PresensiGuruMapel::findOrFail($id);

        try {
            $updated = DB::transaction(function () use ($request, $presensi) {
                $presensi->update([
                    'materi' => $request->input('materi', $presensi->materi),
                    'tanggal' => $request->input('tanggal', $presensi->tanggal),
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
                'message' => 'Data berhasil diperbarui oleh Kesiswaan.',
                'data' => new PresensiGuruMapelResource($updated)
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
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

        $data = $jadwal->map(fn($j) => [
            'guru_mapel_id' => $j->id,
            'nama_guru' => $j->guru?->nama,
            'mata_pelajaran' => $j->mapel?->nama_mapel,
            'kelas' => $j->kelas?->nama_kelas,
            'jam' => $j->jam_mulai_id . ' - ' . $j->jam_selesai_id,
            'status' => in_array($j->id, $sudahAbsen) ? 'Sudah Absen' : 'Belum Absen'
        ]);

        return response()->json(['success' => true, 'data' => $data], Response::HTTP_OK);
    }

    public function export(Request $request)
    {
        $bulan = $request->query('bulan', date('m'));
        $tahun = $request->query('tahun', date('Y'));
        $kelasId = $request->query('kelas_id');
        $guruStafId = $request->query('guru_staf_id');

        if (!$kelasId) {
            return response()->json(['success' => false, 'message' => 'Pilih kelas terlebih dahulu.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $query = PresensiGuruMapel::with([
            'presensiSiswaDetail.siswa', 
            'guruMapel.guru', 
            'mataPelajaran', 
            'kelas'
        ])
        ->whereMonth('tanggal', $bulan)
        ->whereYear('tanggal', $tahun)
        ->where('kelas_id', $kelasId);

        if ($guruStafId) {
            $query->whereHas('guruMapel', fn($q) => $q->where('guru_staf_id', $guruStafId));
        }

        $namaKelas = DB::table('kelas')->where('id', $kelasId)->value('nama_kelas') ?? $kelasId;
        $taAktif = DB::table('tahun_ajaran')->where('is_active', 1)->first();
        $taData = DB::table('tahun_ajaran')->where('id', $request->query('tahun_ajaran_id') ?? $taAktif?->id)->first();
        
        $profil = DB::table('profil_sekolah')->first();
        $kontak = DB::table('data_kontak')->first();
        $guruTarget = $guruStafId ? DB::table('guru_staf')->where('id', $guruStafId)->first() : null;

        return Excel::download(
            new PresensiGuruMapelExport(
                $query, 
                "Bulan-{$bulan}-{$tahun}", 
                $profil, 
                $kontak,
                (object)[
                    'nama' => $guruTarget->nama ?? 'Semua Guru', 
                    'nip' => $guruTarget->nip ?? '-'
                ],
                $taData ? $taData->nama . " (" . $taData->semester . ")" : '-',
                true, 
                $bulan,
                $tahun
            ),
            "Rekap_Kesiswaan_Kelas_{$namaKelas}_Bulan_{$bulan}_{$tahun}.xlsx"
        );
    }
}