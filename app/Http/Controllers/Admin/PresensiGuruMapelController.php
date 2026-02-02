<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PresensiGuruMapel;
use App\Models\GuruMapel;
<<<<<<< HEAD
use App\Http\Requests\StorePresensiGuruMapelRequest;
use App\Http\Requests\UpdatePresensiGuruMapelRequest;
use App\Http\Resources\PresensiGuruMapelResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use BadMethodCallException;
use Throwable;

class PresensiGuruMapelController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:admin')->only(['destroy']);
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    protected function isAdmin($user): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('admin') || $user->hasRole('Admin');
        }

        return isset($user->role_id) && (int) $user->role_id === 2;
    }

    protected function applyGuruMapelOwnershipQuery($query, $user)
    {
        $applied = false;

        try {
            $query->where(function ($q) use ($user, &$applied) {
                try {
                    $q->whereHas('guruStaf', fn($r) => $r->where('user_id', $user->id));
                    $applied = true;
                } catch (BadMethodCallException $e) {
                    //
                }

                try {
                    $q->orWhereHas('guru', fn($r) => $r->where('user_id', $user->id));
                    $applied = true;
                } catch (BadMethodCallException $e) {
                    //
                }

                if (! $applied) {
                    $candidates = [
                        'guru_staf',
                        'guru_staf_id',
                        'id_guru_staff',
                        'guru_id',
                        'guru_staff_id',
                        'staf'
                    ];

                    foreach ($candidates as $col) {
                        if (Schema::hasColumn('guru_mapel', $col)) {
                            $q->orWhere($col, $user->guru?->id ?? $user->id);
                            $applied = true;
                            break;
                        }
                    }
                }
            });
        } catch (Throwable $e) {
            Log::warning('applyGuruMapelOwnershipQuery failed', ['error' => $e->getMessage()]);
        }

        return $query;
=======
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
>>>>>>> master
    }

    public function index(Request $request): JsonResponse
    {
<<<<<<< HEAD
        $this->authorize('viewAny', PresensiGuruMapel::class);

        $user = $request->user();

        $query = PresensiGuruMapel::with([
            'presensiSiswaDetail.siswa',
            'guruMapel',
            'mataPelajaran',
            'kelas'
        ]);

        if (! $this->isAdmin($user)) {
            $query->whereHas('guruMapel', fn($q) => $this->applyGuruMapelOwnershipQuery($q, $user));
        }

=======
        $query = PresensiGuruMapel::with([
            'presensiSiswaDetail.siswa', 
            'guruMapel.guru', 
            'mataPelajaran', 
            'kelas'
        ]);

>>>>>>> master
        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        }

<<<<<<< HEAD
=======
        if ($request->filled('guru_staf_id')) {
            $query->whereHas('guruMapel', fn($q) => $q->where('guru_staf_id', $request->guru_staf_id));
        }

>>>>>>> master
        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', $request->kelas_id);
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
<<<<<<< HEAD

        return PresensiGuruMapelResource::collection(
            $query->latest()->paginate($perPage)
        )->response();
=======
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
>>>>>>> master
    }

    public function store(StorePresensiGuruMapelRequest $request): JsonResponse
    {
<<<<<<< HEAD
        $this->authorize('create', PresensiGuruMapel::class);

        $user = $request->user();
        $guruMapelId = $request->input('guru_mapel_id');

        if ($this->isAdmin($user)) {
            if (empty($guruMapelId) && $request->filled('mata_pelajaran_id')) {
                $relasi = GuruMapel::where('mata_pelajaran_id', $request->input('mata_pelajaran_id'))->first();
                $guruMapelId = $relasi->id ?? null;
            }
        } else {
            if (! $request->filled('mata_pelajaran_id') && ! $request->filled('guru_mapel_id')) {
                return response()->json(['message' => 'Field mata_pelajaran_id atau guru_mapel_id diperlukan untuk guru.'], 422);
            }

            if ($request->filled('guru_mapel_id')) {
                $relasiQuery = GuruMapel::where('id', $request->input('guru_mapel_id'));
            } else {
                $relasiQuery = GuruMapel::where('mata_pelajaran_id', $request->input('mata_pelajaran_id'));
            }

            $this->applyGuruMapelOwnershipQuery($relasiQuery, $user);

            $relasi = $relasiQuery->first();

            if (! $relasi) {
                return response()->json(['message' => 'Akses ditolak. Anda bukan guru mapel untuk mata pelajaran/kelas ini.'], 403);
            }

            $guruMapelId = $relasi->id;
        }

        if (empty($guruMapelId)) {
            return response()->json(['message' => 'Field guru_mapel_id tidak boleh kosong.'], 422);
        }

        $presensiItems = $request->input('presensi', []);
        if (! is_array($presensiItems)) {
            return response()->json(['message' => 'Format presensi tidak valid.'], 422);
        }

        try {
            $presensi = DB::transaction(function () use ($request, $guruMapelId, $presensiItems) {
                $header = PresensiGuruMapel::create([
                    'guru_mapel_id'     => $guruMapelId,
                    'kelas_id'          => $request->input('kelas_id'),
                    'mata_pelajaran_id' => $request->input('mata_pelajaran_id'),
                    'tanggal'           => $request->input('tanggal'),
                    'jam_masuk'         => $request->input('jam_masuk'),
                    'jam_keluar'        => $request->input('jam_keluar'),
                    'materi'            => $request->input('materi'),
                ]);

                collect($presensiItems)->each(function ($item) use ($header) {
                    if (empty($item['siswa_id']) || ! isset($item['status'])) {
                        return;
                    }

                    $header->presensiSiswaDetail()->create([
                        'siswa_id' => $item['siswa_id'],
                        'status'   => $item['status'],
                        'catatan'  => $item['catatan'] ?? null,
                    ]);
                });

                return $header->load('presensiSiswaDetail.siswa', 'guruMapel', 'mataPelajaran', 'kelas');
=======
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
>>>>>>> master
            });

            return (new PresensiGuruMapelResource($presensi))
                ->response()
<<<<<<< HEAD
                ->setStatusCode(201);

        } catch (Throwable $e) {
            Log::error('Presensi Store Error', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);
            return response()->json(['message' => 'Gagal menyimpan data.'], 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $presensi = PresensiGuruMapel::with([
            'presensiSiswaDetail.siswa',
            'guruMapel',
            'mataPelajaran',
            'kelas'
        ])->findOrFail($id);

        $this->authorize('view', $presensi);

        return (new PresensiGuruMapelResource($presensi))->response();
    }

    public function update(UpdatePresensiGuruMapelRequest $request, int $id): JsonResponse
    {
        $header = PresensiGuruMapel::findOrFail($id);

        $this->authorize('update', $header);

        $user = $request->user();
        if (! $this->isAdmin($user)) {
            $ownerMatch = false;

            try {
                $gm = $header->guruMapel;
                if ($gm) {
                    if (isset($gm->guru_staf) && (string) $gm->guru_staf === (string) ($user->guru?->id ?? '')) {
                        $ownerMatch = true;
                    } elseif (isset($gm->guru_staf_id) && (string) $gm->guru_staf_id === (string) ($user->guru?->id ?? '')) {
                        $ownerMatch = true;
                    } elseif (isset($gm->id_guru_staff) && (string) $gm->id_guru_staff === (string) ($user->guru?->id ?? '')) {
                        $ownerMatch = true;
                    } elseif (isset($gm->guru_id) && (string) $gm->guru_id === (string) ($user->guru?->id ?? '')) {
                        $ownerMatch = true;
                    }
                }
            } catch (Throwable $e) {
                Log::warning('Owner check failed', ['error' => $e->getMessage()]);
            }

            if (! $ownerMatch) {
                $exists = GuruMapel::where('id', $header->guru_mapel_id);
                $this->applyGuruMapelOwnershipQuery($exists, $user);
                if (! $exists->exists()) {
                    return response()->json(['message' => 'Akses ditolak. Anda bukan guru mapel pemilik presensi ini.'], 403);
                }
            }
        }

        $presensiItems = $request->input('presensi', []);
        if (! is_array($presensiItems) && $request->filled('presensi')) {
            return response()->json(['message' => 'Format presensi tidak valid.'], 422);
        }

        try {
            DB::transaction(function () use ($request, $header, $presensiItems) {
                $header->update($request->only(['jam_masuk', 'jam_keluar', 'materi', 'tanggal']));

                if (! empty($presensiItems)) {
                    collect($presensiItems)->each(function ($item) use ($header) {
                        if (empty($item['siswa_id']) || ! isset($item['status'])) {
                            return;
                        }

                        $header->presensiSiswaDetail()->updateOrCreate(
                            ['siswa_id' => $item['siswa_id']],
                            [
                                'status'  => $item['status'],
                                'catatan' => $item['catatan'] ?? null
                            ]
                        );
                    });
                }
            });

            $header->load(['presensiSiswaDetail.siswa', 'guruMapel', 'mataPelajaran', 'kelas']);

            return (new PresensiGuruMapelResource($header))
                ->response()
                ->setStatusCode(200);

        } catch (Throwable $e) {
            Log::error('Presensi Update Error', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
                'presensi_id' => $id,
            ]);
            return response()->json(['message' => 'Gagal memperbarui data.'], 500);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $presensi = PresensiGuruMapel::findOrFail($id);

        $this->authorize('delete', $presensi);

        $presensi->delete();

        return response()->json(['message' => 'Data dihapus.']);
    }
}
=======
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
>>>>>>> master
