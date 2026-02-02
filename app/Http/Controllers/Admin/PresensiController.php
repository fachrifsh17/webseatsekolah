<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Presensi;
use App\Models\Siswa;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Http\Requests\StorePresensiRequest;
use App\Http\Requests\UpdatePresensiRequest;
use App\Http\Resources\PresensiResource;
<<<<<<< HEAD
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Request as RequestFacade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
=======
use App\Exports\PresensiExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
>>>>>>> master
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class PresensiController extends Controller
{
<<<<<<< HEAD
    protected function isAdminUser($user): bool
    {
        if (!$user || !method_exists($user, 'hasRole')) return false;
        return $user->hasRole('admin') || $user->hasRole('Admin') || $user->hasRole('ADMIN');
    }

    protected function findKelasDiwalikanByGuruStaf($guruStaf)
    {
        $guruStafId = optional($guruStaf)->id;
        return $guruStafId ? Kelas::where('wali_kelas_id', (string) $guruStafId)->first() : null;
=======
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
>>>>>>> master
    }

    public function index(Request $request): JsonResponse
    {
<<<<<<< HEAD
        try {
            $this->authorize('viewAny', Presensi::class);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }

        $user = $request->user();
        $isAdmin = $this->isAdminUser($user);
        $kelasObj = $this->findKelasDiwalikanByGuruStaf($user->guruStaf);
        $kelasId = optional($kelasObj)->id;

        $query = Presensi::with(['siswa.kelas', 'tahunAjaran', 'guruStaf']);

        if (!$isAdmin) {
            if (!$kelasId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses kelas perwalian.'
                ], Response::HTTP_FORBIDDEN);
            }
            $query->whereHas('siswa', fn($q) => $q->where('kelas_id', (string) $kelasId));
        } elseif ($request->filled('kelas_id')) {
            $query->whereHas('siswa', fn($q) => $q->where('kelas_id', (string) $request->kelas_id));
        }

        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        }

        $perPage = min((int) $request->get('per_page', 50), 100);
        $data = $query->latest()->paginate($perPage);
=======
        $query = Presensi::query();
        $query = $this->applyPresensiFilters($request, $query);

        $perPage = min((int) $request->get('per_page', 50), 100);
        $data = $query->paginate($perPage);
>>>>>>> master

        return response()->json([
            'success' => true,
            'data'    => PresensiResource::collection($data),
            'meta'    => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'per_page'     => $data->perPage(),
                'total'        => $data->total(),
            ],
        ], Response::HTTP_OK);
    }

<<<<<<< HEAD
    /**
     * Menampilkan daftar siswa di kelas perwalian
     */
    public function siswaWali(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Siswa::class);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }

        $user = $request->user();
        $isAdmin = $this->isAdminUser($user);

        if ($isAdmin && $request->filled('kelas_id')) {
            $kelasId = (string) $request->kelas_id;
        } else {
            $kelasId = optional($this->findKelasDiwalikanByGuruStaf($user->guruStaf))->id;
            if (!$kelasId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data kelas perwalian tidak ditemukan.'
                ], Response::HTTP_FORBIDDEN);
            }
            $kelasId = (string) $kelasId;
        }

        $tanggal = $request->get('tanggal', date('Y-m-d'));

        $siswaQuery = Siswa::where('kelas_id', $kelasId)
            ->with(['kelas', 'presensi' => function ($q) use ($tanggal) {
                $q->whereDate('tanggal', $tanggal);
            }])
            ->when($request->filled('q'), fn($q) => $q->where('nama_lengkap', 'like', "%{$request->q}%"))
            ->orderBy('nama_lengkap', 'asc');

        $perPage = min((int) $request->get('per_page', 50), 100);
        $siswa = $siswaQuery->paginate($perPage);

        // Transform collection into PresensiResource per siswa (mengembalikan presensi hari itu atau placeholder)
        $collection = $siswa->getCollection()->map(function ($item) use ($tanggal) {
=======
    public function show(Presensi $presensi): JsonResponse
    {
        return response()->json([
            'success' => true, 
            'data' => new PresensiResource($presensi->load(['siswa.kelas', 'guruStaf']))
        ], Response::HTTP_OK);
    }

    public function store(StorePresensiRequest $request): JsonResponse
    {
        $user = Auth::user();
        $tanggalInput = $request->filled('tanggal') ? date('Y-m-d', strtotime($request->tanggal)) : date('Y-m-d');

        if ($this->isDayOff($tanggalInput)) {
            return response()->json([
                'success' => false, 
                'message' => 'Hari libur atau akhir pekan.'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $tahunAjaran = TahunAjaran::where('is_active', true)->firstOrFail();
            $dataInput = $request->has('data_presensi') ? $request->input('data_presensi') : [$request->all()];

            $results = DB::transaction(function () use ($dataInput, $tanggalInput, $user, $tahunAjaran) {
                $savedData = [];
                foreach ($dataInput as $item) {
                    if (!isset($item['siswa_id']) || !isset($item['status'])) continue;

                    $siswa = Siswa::whereHas('kelas', fn($q) => $q->where('is_active', true))
                        ->find($item['siswa_id']);
                    
                    if (!$siswa) continue;

                    $presensi = Presensi::updateOrCreate(
                        [
                            'siswa_id' => (string) $item['siswa_id'], 
                            'tanggal' => $tanggalInput, 
                            'tahun_ajaran_id' => (string) $tahunAjaran->id
                        ],
                        [
                            'status' => $item['status'],
                            'keterangan' => $item['keterangan'] ?? 'Diinput oleh Admin: ' . $user->username,
                            'guru_staf_id' => (string) ($siswa->kelas->wali_kelas_id ?? $user->guru_staf_id),
                        ]
                    );
                    $savedData[] = $presensi;
                }
                return $savedData;
            });

            return response()->json([
                'success' => true, 
                'message' => 'Data presensi berhasil diproses.',
                'count' => count($results)
            ], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            Log::error($e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal simpan data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdatePresensiRequest $request, Presensi $presensi): JsonResponse
    {
        try {
            $presensi->update($request->validated());
            return response()->json([
                'success' => true, 
                'data' => new PresensiResource($presensi->load(['siswa.kelas', 'guruStaf']))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal update.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        $profil = DB::table('profil_sekolah')->first();
        $kontak = DB::table('data_kontak')->first();
        
        $month = $request->get('bulan', date('m'));
        $year = $request->get('tahun', date('Y'));
        $labelWaktu = "Bulan-" . $month . "-" . $year;

        $query = Presensi::query();
        $filteredQuery = $this->applyPresensiFilters($request, $query);

        $taAktif = TahunAjaran::where('is_active', true)->first();
        $taId = $request->get('tahun_ajaran_id') ?? $taAktif?->id;
        $taData = DB::table('tahun_ajaran')->where('id', $taId)->first();
        $tahunAjaranLabel = $taData ? $taData->nama . " (" . $taData->semester . ")" : '-';

        $dataKelas = $request->filled('kelas_id') ? Kelas::where('is_active', true)->with('waliKelas')->find($request->kelas_id) : null;
        $namaKelas = $dataKelas ? $dataKelas->nama_kelas : "Semua Kelas";

        return Excel::download(
            new PresensiExport(
                $filteredQuery, 
                $namaKelas, 
                $labelWaktu, 
                $profil, 
                $kontak, 
                $dataKelas, 
                'kesiswaan',
                $tahunAjaranLabel
            ), 
            'presensi_' . now()->format('YmdHis') . '.xlsx'
        );
    }

    public function siswaWali(Request $request): JsonResponse
    {
        $tanggal = $request->get('tanggal', date('Y-m-d'));
        $kelasId = $request->kelas_id;
        $semester = $request->semester;
        
        if ($request->filled('tahun_ajaran_id')) {
            $tahunAjaranId = $request->tahun_ajaran_id;
        } else {
            $tahunAktif = TahunAjaran::where('is_active', true)->first();
            $tahunAjaranId = $tahunAktif?->id;
        }

        if (!$kelasId) {
            return response()->json(['success' => false, 'message' => 'Parameter kelas_id wajib diisi.'], Response::HTTP_BAD_REQUEST);
        }

        $siswa = Siswa::where('kelas_id', $kelasId)
            ->whereHas('kelas', fn($q) => $q->where('is_active', true))
            ->with(['kelas', 'presensi' => function($q) use ($tanggal, $tahunAjaranId, $semester) {
                $q->whereDate('tanggal', $tanggal);
                if ($tahunAjaranId) {
                    $q->where('tahun_ajaran_id', $tahunAjaranId);
                }
                if ($semester) {
                    $q->whereHas('tahunAjaran', fn($sq) => $sq->where('semester', $semester));
                }
            }])
            ->when($request->filled('search'), fn($q) => $q->where('nama_lengkap', 'like', "%{$request->search}%"))
            ->orderBy('nama_lengkap', 'asc')
            ->get();

        $collection = $siswa->map(function ($item) use ($tanggal) {
>>>>>>> master
            $presensi = $item->presensi->first() ?? new Presensi([
                'siswa_id' => (string) $item->id,
                'tanggal'  => $tanggal,
                'status'   => null
            ]);
            $presensi->setRelation('siswa', $item);
            return new PresensiResource($presensi);
<<<<<<< HEAD
        })->values();

        return response()->json([
            'success' => true,
            'data'    => $collection,
            'meta'    => [
                'current_page' => $siswa->currentPage(),
                'last_page'    => $siswa->lastPage(),
                'per_page'     => $siswa->perPage(),
                'total'        => $siswa->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePresensiRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // Cast siswa_id to string to match varchar PKs
        $siswaId = (string) ($validated['siswa_id'] ?? null);
        $siswa = Siswa::findOrFail($siswaId);

        try {
            $this->authorize('createPresensiFor', $siswa);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }

        $tahunAjaran = TahunAjaran::where('is_active', true)->firstOrFail();

        $isRouteAdmin = RequestFacade::is('api/admin/*') || RequestFacade::is('admin/*');
        $guruStafId = $isRouteAdmin ? ($request->guru_staf_id ?? optional($user->guruStaf)->id) : optional($user->guruStaf)->id;
        $guruStafId = $guruStafId !== null ? (string) $guruStafId : null;

        try {
            $presensi = DB::transaction(function () use ($siswaId, $validated, $guruStafId, $tahunAjaran) {
                return Presensi::updateOrCreate(
                    ['siswa_id' => $siswaId, 'tanggal' => $validated['tanggal']],
                    [
                        'status'          => $validated['status'],
                        'keterangan'      => $validated['keterangan'] ?? null,
                        'guru_staf_id'    => $guruStafId,
                        'tahun_ajaran_id' => (string) $tahunAjaran->id,
                    ]
                );
            });

            $presensi->load(['siswa.kelas', 'guruStaf', 'tahunAjaran']);

            return response()->json([
                'success' => true,
                'message' => 'Presensi berhasil disimpan.',
                'data'    => new PresensiResource($presensi),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Failed to store presensi', [
                'siswa_id' => $siswaId,
                'payload'  => $validated,
                'error'    => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan presensi.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdatePresensiRequest $request, Presensi $presensi): JsonResponse
    {
        try {
            $this->authorize('update', $presensi);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validated();

        if ((RequestFacade::is('api/admin/*') || RequestFacade::is('admin/*')) && $request->filled('guru_staf_id')) {
            $presensi->guru_staf_id = (string) $request->guru_staf_id;
        }

        try {
            DB::transaction(function () use ($presensi, $validated) {
                $presensi->update($validated);
            });

            $presensi->load(['siswa.kelas', 'guruStaf', 'tahunAjaran']);

            return response()->json([
                'success' => true,
                'message' => 'Presensi berhasil diperbarui.',
                'data'    => new PresensiResource($presensi),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to update presensi', [
                'presensi_id' => (string) $presensi->id,
                'payload'     => $validated,
                'error'       => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui presensi.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Presensi $presensi): JsonResponse
    {
        try {
            $this->authorize('view', $presensi);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }

        $presensi->load(['siswa.kelas', 'guruStaf', 'tahunAjaran']);

        return response()->json([
            'success' => true,
            'data'    => new PresensiResource($presensi),
        ], Response::HTTP_OK);
=======
        });

        return response()->json(['success' => true, 'data' => $collection], Response::HTTP_OK);
>>>>>>> master
    }

    public function destroy(Presensi $presensi): JsonResponse
    {
<<<<<<< HEAD
        try {
            $this->authorize('delete', $presensi);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            DB::transaction(fn() => $presensi->delete());

            return response()->json([
                'success' => true,
                'message' => 'Data presensi dihapus.'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to delete presensi', ['presensi_id' => (string) $presensi->id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus presensi.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
=======
        $presensi->delete();
        return response()->json(['success' => true, 'message' => 'Dihapus.'], Response::HTTP_OK);
    }

    private function applyPresensiFilters(Request $request, $query)
    {
        $taId = $request->tahun_ajaran_id;
        $semester = $request->semester;

        if (!$taId) {
            $tahunAktif = TahunAjaran::where('is_active', true)->first();
            $taId = $tahunAktif?->id;
        }

        if ($taId) {
            $query->where('tahun_ajaran_id', $taId);
        }

        if ($semester) {
            $query->whereHas('tahunAjaran', fn($q) => $q->where('semester', $semester));
        }
        
        $query->whereHas('siswa.kelas', fn($q) => $q->where('is_active', true));

        if ($request->filled('kelas_id')) {
            $query->whereHas('siswa', fn($q) => $q->where('kelas_id', (string) $request->kelas_id));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('siswa', fn($qs) => $qs->where('nama_lengkap', 'like', "%{$search}%"))
                  ->orWhere('keterangan', 'like', "%{$search}%");
            });
        }

        if ($request->filled('bulan') && $request->filled('tahun')) {
            $query->whereMonth('tanggal', $request->bulan)->whereYear('tanggal', $request->tahun);
        } elseif ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        }

        return $query->with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])->orderBy('tanggal', 'desc');
    }

    private function isDayOff($date): bool
    {
        $libur = DB::table('kalender_akademik')->where('kategori', 'Libur')
            ->whereDate('tanggal_mulai', '<=', $date)
            ->whereDate('tanggal_selesai', '>=', $date)->first();
        return $libur || date('N', strtotime($date)) >= 6;
    }
}
>>>>>>> master
