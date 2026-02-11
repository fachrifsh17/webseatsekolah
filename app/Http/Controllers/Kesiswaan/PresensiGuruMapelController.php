<?php

namespace App\Http\Controllers\Kesiswaan;

use App\Http\Controllers\Controller;
use App\Models\{PresensiGuruMapel, GuruMapel, TahunAjaran, Kelas, Siswa};
use App\Http\Resources\PresensiGuruMapelResource;
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
        $this->middleware('log.aktivitas')->only(['update']);
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
        $this->authorize('viewAny', PresensiGuruMapel::class);

        try {
            $query = PresensiGuruMapel::with([
                'guruMapel.guru', 
                'mapel', 
                'guruMapel.jamMulai',
                'guruMapel.jamSelesai',
                'jamMasukDetail',
                'jamKeluarDetail',
                'kelas',
                'tahunAjaran'
            ]);

            if (!$request->has('show_all')) {
                $query->whereHas('mapel', fn($q) => $q->where('is_active', 1));
            }

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
                    'total' => $paginasi->total(),
                ]
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Kesiswaan Index Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengambil data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $presensi = PresensiGuruMapel::with([
                'getBySiswaDetil.siswa',
                'guruMapel.guru',
                'mapel',
                'guruMapel.jamMulai',
                'guruMapel.jamSelesai',
                'jamMasukDetail',
                'jamKeluarDetail',
                'kelas',
                'tahunAjaran'
            ])->findOrFail($id);

            $this->authorize('view', $presensi);

            return response()->json([
                'success' => true,
                'data' => new PresensiGuruMapelResource($presensi)
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], Response::HTTP_NOT_FOUND);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        $presensi = PresensiGuruMapel::findOrFail($id);
        $this->authorize('update', $presensi);

        try {
            $updated = DB::transaction(function () use ($request, $presensi) {
                $presensi->update([
                    'materi' => $request->input('materi', $presensi->materi),
                    'tanggal' => $request->input('tanggal', $presensi->tanggal),
                ]);

                if ($request->has('presensi')) {
                    foreach ($request->input('presensi') as $item) {
                        $presensi->getBySiswaDetil()
                            ->where('siswa_id', $item['siswa_id'])
                            ->update([
                                'status' => $item['status'],
                                'catatan' => $item['catatan'] ?? null
                            ]);
                    }
                }

                return $presensi->fresh()->load([
                    'getBySiswaDetil.siswa', 
                    'guruMapel.guru', 
                    'mapel', 
                    'kelas',
                    'jamMasukDetail',
                    'jamKeluarDetail'
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil diperbarui oleh Kesiswaan.',
                'data' => new PresensiGuruMapelResource($updated)
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Kesiswaan Update Jurnal Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function listJadwalHariIni(): JsonResponse
    {
        $this->authorize('viewAny', PresensiGuruMapel::class);

        $hariIni = Carbon::now('Asia/Jakarta')->locale('id')->dayName;

        $jadwal = GuruMapel::with(['mapel', 'kelas', 'guru', 'jamMulai', 'jamSelesai'])
            ->whereHas('mapel', fn($q) => $q->where('is_active', 1))
            ->where('hari', $hariIni)
            ->get();

        $sudahAbsen = PresensiGuruMapel::whereDate('tanggal', Carbon::today())
            ->pluck('guru_mapel_id')
            ->toArray();

        $data = $jadwal->map(function($j) use ($sudahAbsen) {
            $mulai = $j->jamMulai?->jam_ke;
            $selesai = $j->jamSelesai?->jam_ke;

            return [
                'guru_mapel_id' => $j->id,
                'nama_guru' => $j->guru?->nama,
                'mata_pelajaran' => $j->mapel?->nama_mapel,
                'kelas' => $j->kelas?->nama_kelas,
                'jam' => ($mulai && $selesai) ? "Jam Ke $mulai - $selesai" : "-",
                'status' => in_array($j->id, $sudahAbsen) ? 'Sudah Absen' : 'Belum Absen'
            ];
        });

        return response()->json(['success' => true, 'data' => $data], Response::HTTP_OK);
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', PresensiGuruMapel::class);

        try {
            if (!$request->filled('kelas_id')) {
                return response()->json(['success' => false, 'message' => 'Pilih kelas terlebih dahulu.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $ta = $request->filled('tahun_ajaran_id') 
                ? TahunAjaran::find($request->tahun_ajaran_id) 
                : TahunAjaran::where('is_active', true)->first();

            $bulan = (int) $request->get('bulan', date('m'));
            $tahun = $this->determineYear($ta, $bulan);

            $query = PresensiGuruMapel::query()
                ->whereMonth('tanggal', $bulan)
                ->whereYear('tanggal', $tahun)
                ->where('kelas_id', $request->kelas_id);

            if ($request->filled('guru_staf_id')) {
                $query->whereHas('guruMapel', fn($q) => $q->where('guru_staf_id', $request->guru_staf_id));
            }

            $namaKelas = Kelas::where('id', $request->kelas_id)->value('nama_kelas') ?? 'Unknown';
            $guruTarget = $request->filled('guru_staf_id') ? DB::table('guru_staf')->where('id', $request->guru_staf_id)->first() : null;

            return Excel::download(
                new PresensiGuruMapelExport(
                    $query, 
                    "Bulan-{$bulan}-{$tahun}", 
                    DB::table('profil_sekolah')->first(), 
                    DB::table('data_kontak')->first(),
                    (object)[
                        'nama' => $guruTarget->nama ?? 'Semua Guru', 
                        'nip' => $guruTarget->nip ?? '-'
                    ],
                    $ta ? ($ta->nama . " (" . $ta->semester . ")") : '-',
                    true, 
                    $bulan,
                    $tahun
                ),
                "Rekap_Kesiswaan_Kelas_{$namaKelas}_Bulan_{$bulan}.xlsx"
            );
        } catch (Throwable $e) {
            Log::error('Kesiswaan Export Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengekspor data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}