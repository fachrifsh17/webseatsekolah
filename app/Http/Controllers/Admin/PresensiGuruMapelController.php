<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PresensiGuruMapel;
use App\Models\GuruMapel;
use App\Models\Siswa;
use App\Http\Requests\StorePresensiGuruMapelRequest;
use App\Http\Requests\UpdatePresensiGuruMapelRequest;
use App\Http\Resources\PresensiGuruMapelResource;
use App\Exports\PresensiGuruMapelExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        $this->middleware('role:admin');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PresensiGuruMapel::class);

        $query = PresensiGuruMapel::with([
            'presensiSiswaDetail.siswa',
            'guruMapel.guru',
            'mataPelajaran',
            'kelas'
        ]);

        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        }

        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', $request->kelas_id);
        }

        if ($request->filled('guru_staf_id')) {
            $query->whereHas('guruMapel', fn($q) => $q->where('guru_staf_id', $request->guru_staf_id));
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
    }

    public function show($id): JsonResponse
    {
        $presensi = PresensiGuruMapel::with([
            'presensiSiswaDetail.siswa',
            'guruMapel.guru',
            'mataPelajaran',
            'kelas'
        ])->findOrFail($id);

        $this->authorize('view', $presensi);

        return response()->json([
            'success' => true,
            'data' => new PresensiGuruMapelResource($presensi)
        ], Response::HTTP_OK);
    }

    public function store(StorePresensiGuruMapelRequest $request): JsonResponse
    {
        $this->authorize('create', PresensiGuruMapel::class);
        $relasi = GuruMapel::findOrFail($request->input('guru_mapel_id'));

        try {
            $presensi = DB::transaction(function () use ($request, $relasi) {
                $header = PresensiGuruMapel::updateOrCreate(
                    ['guru_mapel_id' => $relasi->id, 'tanggal' => $request->input('tanggal', Carbon::today()->toDateString())],
                    [
                        'kelas_id' => $relasi->kelas_id,
                        'mata_pelajaran_id' => $relasi->mata_pelajaran_id,
                        'jam_masuk' => $request->input('jam_masuk', $relasi->jam_mulai_id),
                        'jam_keluar' => $request->input('jam_keluar', $relasi->jam_selesai_id),
                        'materi' => $request->input('materi'),
                    ]
                );

                $inputPresensi = collect($request->input('presensi', []));
                $semuaSiswaIds = Siswa::where('kelas_id', $relasi->kelas_id)->where('is_active', true)->pluck('id');

                foreach ($semuaSiswaIds as $siswaId) {
                    $dataSiswa = $inputPresensi->firstWhere('siswa_id', $siswaId);
                    $header->presensiSiswaDetail()->updateOrCreate(
                        ['siswa_id' => $siswaId],
                        [
                            'status' => $dataSiswa['status'] ?? 'hadir',
                            'catatan' => $dataSiswa['catatan'] ?? null
                        ]
                    );
                }
                return $header->load(['presensiSiswaDetail.siswa', 'guruMapel.guru', 'mataPelajaran', 'kelas']);
            });

            return (new PresensiGuruMapelResource($presensi))->response()->setStatusCode(Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Admin Simpan Jurnal Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal simpan data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdatePresensiGuruMapelRequest $request, $id): JsonResponse
    {
        $presensi = PresensiGuruMapel::findOrFail($id);
        $this->authorize('update', $presensi);

        try {
            $updated = DB::transaction(function () use ($request, $presensi) {
                $presensi->update($request->only(['materi', 'jam_masuk', 'jam_keluar', 'tanggal']));

                if ($request->has('presensi')) {
                    foreach ($request->input('presensi') as $item) {
                        $presensi->presensiSiswaDetail()->where('siswa_id', $item['siswa_id'])
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
        } catch (Throwable $e) {
            Log::error('Admin Update Jurnal Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal update data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy($id): JsonResponse
    {
        $presensi = PresensiGuruMapel::findOrFail($id);
        $this->authorize('delete', $presensi);

        try {
            $presensi->delete();
            return response()->json([
                'success' => true, 
                'message' => 'Jurnal berhasil dihapus.'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menghapus data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', PresensiGuruMapel::class);
        
        $bulan = $request->query('bulan', date('m'));
        $tahun = $request->query('tahun', date('Y'));
        $kelasId = $request->query('kelas_id');
        $guruStafId = $request->query('guru_staf_id');
        
        if (!$kelasId) {
            return response()->json(['success' => false, 'message' => 'Pilih kelas terlebih dahulu.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $query = PresensiGuruMapel::query()
            ->with(['guruMapel.guru', 'mataPelajaran', 'kelas'])
            ->whereMonth('tanggal', $bulan)
            ->whereYear('tanggal', $tahun)
            ->where('kelas_id', $kelasId);

        if ($guruStafId) {
            $query->whereHas('guruMapel', function($q) use ($guruStafId) {
                $q->where('guru_staf_id', $guruStafId);
            });
        }

        $namaKelas = DB::table('kelas')->where('id', $kelasId)->value('nama_kelas') ?? $kelasId;
        $taAktif = DB::table('tahun_ajaran')->where('is_active', 1)->first();
        $taData = DB::table('tahun_ajaran')->where('id', $request->query('tahun_ajaran_id') ?? $taAktif?->id)->first();
        $guruTarget = $guruStafId ? DB::table('guru_staf')->where('id', $guruStafId)->first() : null;

        return Excel::download(
            new PresensiGuruMapelExport(
                $query, 
                "Bulan-{$bulan}-{$tahun}", 
                DB::table('profil_sekolah')->first(), 
                DB::table('data_kontak')->first(),
                (object)['nama' => $guruTarget->nama ?? 'Semua Guru', 'nip' => $guruTarget->nip ?? '-'],
                $taData ? ($taData->nama . " (" . $taData->semester . ")") : '-',
                true, 
                $bulan,
                $tahun
            ),
            "Rekap_Presensi_Kelas_{$namaKelas}_Bulan_{$bulan}_{$tahun}.xlsx"
        );
    }
}