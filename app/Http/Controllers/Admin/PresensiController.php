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
use App\Exports\PresensiExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Request as RequestFacade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class PresensiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    /**
     * Helper untuk mengecek role admin
     */
    protected function isAdminUser($user): bool
    {
        if (!$user || !method_exists($user, 'hasRole')) return false;
        return $user->hasAnyRole(['admin', 'Admin', 'ADMIN']);
    }

    /**
     * Helper mencari kelas perwalian guru
     */
    protected function findKelasDiwalikanByGuruStaf($guruStaf)
    {
        $guruStafId = optional($guruStaf)->id;
        return $guruStafId ? Kelas::where('wali_kelas_id', (string) $guruStafId)->first() : null;
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Presensi::class);

        $query = Presensi::query();
        
        // Proteksi Data: Jika bukan admin, hanya bisa melihat kelas perwaliannya
        $user = Auth::user();
        if (!$this->isAdminUser($user)) {
            $kelasObj = $this->findKelasDiwalikanByGuruStaf($user->guruStaf);
            if (!$kelasObj) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak. Anda bukan wali kelas.'], Response::HTTP_FORBIDDEN);
            }
            $query->whereHas('siswa', fn($q) => $q->where('kelas_id', (string) $kelasObj->id));
        }

        $query = $this->applyPresensiFilters($request, $query);

        $perPage = min((int) $request->get('per_page', 50), 100);
        $data = $query->paginate($perPage);

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

    public function siswaWali(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Siswa::class);

        $user = Auth::user();
        $isAdmin = $this->isAdminUser($user);

        // Penentuan Kelas ID
        if ($isAdmin && $request->filled('kelas_id')) {
            $kelasId = (string) $request->kelas_id;
        } else {
            $kelasObj = $this->findKelasDiwalikanByGuruStaf($user->guruStaf);
            if (!$kelasObj) {
                return response()->json(['success' => false, 'message' => 'Data kelas perwalian tidak ditemukan.'], Response::HTTP_FORBIDDEN);
            }
            $kelasId = (string) $kelasObj->id;
        }

        $tanggal = $request->get('tanggal', date('Y-m-d'));
        $tahunAjaranId = $request->tahun_ajaran_id ?? TahunAjaran::where('is_active', true)->first()?->id;

        $siswa = Siswa::where('kelas_id', $kelasId)
            ->whereHas('kelas', fn($q) => $q->where('is_active', true))
            ->with(['kelas', 'presensi' => function ($q) use ($tanggal, $tahunAjaranId) {
                $q->whereDate('tanggal', $tanggal);
                if ($tahunAjaranId) $q->where('tahun_ajaran_id', $tahunAjaranId);
            }])
            ->when($request->filled('search'), fn($q) => $q->where('nama_lengkap', 'like', "%{$request->search}%"))
            ->orderBy('nama_lengkap', 'asc')
            ->get();

        $collection = $siswa->map(function ($item) use ($tanggal) {
            $presensi = $item->presensi->first() ?? new Presensi([
                'siswa_id' => (string) $item->id,
                'tanggal'  => $tanggal,
                'status'   => null
            ]);
            $presensi->setRelation('siswa', $item);
            return new PresensiResource($presensi);
        });

        return response()->json(['success' => true, 'data' => $collection], Response::HTTP_OK);
    }

    public function store(StorePresensiRequest $request): JsonResponse
    {
        $user = Auth::user();
        $tanggalInput = $request->filled('tanggal') ? date('Y-m-d', strtotime($request->tanggal)) : date('Y-m-d');

        if ($this->isDayOff($tanggalInput)) {
            return response()->json(['success' => false, 'message' => 'Hari libur atau akhir pekan.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $tahunAjaran = TahunAjaran::where('is_active', true)->firstOrFail();
            $dataInput = $request->has('data_presensi') ? $request->input('data_presensi') : [$request->all()];

            $results = DB::transaction(function () use ($dataInput, $tanggalInput, $user, $tahunAjaran) {
                $savedData = [];
                foreach ($dataInput as $item) {
                    if (!isset($item['siswa_id']) || !isset($item['status'])) continue;

                    $siswa = Siswa::findOrFail($item['siswa_id']);
                    
                    // Otorisasi per siswa (khusus non-admin)
                    if (!$this->isAdminUser($user)) {
                        $this->authorize('createPresensiFor', $siswa);
                    }

                    $presensi = Presensi::updateOrCreate(
                        [
                            'siswa_id' => (string) $item['siswa_id'], 
                            'tanggal' => $tanggalInput, 
                            'tahun_ajaran_id' => (string) $tahunAjaran->id
                        ],
                        [
                            'status' => $item['status'],
                            'keterangan' => $item['keterangan'] ?? 'Diinput oleh: ' . $user->username,
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
            Log::error('Store Presensi Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal simpan data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Presensi $presensi): JsonResponse
    {
        $this->authorize('view', $presensi);
        return response()->json([
            'success' => true, 
            'data' => new PresensiResource($presensi->load(['siswa.kelas', 'guruStaf', 'tahunAjaran']))
        ], Response::HTTP_OK);
    }

    public function update(UpdatePresensiRequest $request, Presensi $presensi): JsonResponse
    {
        $this->authorize('update', $presensi);

        try {
            DB::transaction(fn() => $presensi->update($request->validated()));
            return response()->json([
                'success' => true, 
                'data' => new PresensiResource($presensi->load(['siswa.kelas', 'guruStaf', 'tahunAjaran']))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal update.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Presensi $presensi): JsonResponse
    {
        $this->authorize('delete', $presensi);
        $presensi->delete();
        return response()->json(['success' => true, 'message' => 'Data presensi dihapus.'], Response::HTTP_OK);
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', Presensi::class);
        
        $profil = DB::table('profil_sekolah')->first();
        $kontak = DB::table('data_kontak')->first();
        
        $month = $request->get('bulan', date('m'));
        $year = $request->get('tahun', date('Y'));
        $labelWaktu = "Bulan-" . $month . "-" . $year;

        $query = Presensi::query();
        $filteredQuery = $this->applyPresensiFilters($request, $query);

        $taId = $request->get('tahun_ajaran_id') ?? TahunAjaran::where('is_active', true)->first()?->id;
        $taData = DB::table('tahun_ajaran')->where('id', $taId)->first();
        $tahunAjaranLabel = $taData ? $taData->nama . " (" . $taData->semester . ")" : '-';

        $dataKelas = $request->filled('kelas_id') ? Kelas::with('waliKelas')->find($request->kelas_id) : null;
        $namaKelas = $dataKelas ? $dataKelas->nama_kelas : "Semua Kelas";

        return Excel::download(
            new PresensiExport($filteredQuery, $namaKelas, $labelWaktu, $profil, $kontak, $dataKelas, 'kesiswaan', $tahunAjaranLabel), 
            'presensi_' . now()->format('YmdHis') . '.xlsx'
        );
    }

    private function applyPresensiFilters(Request $request, $query)
    {
        $taId = $request->tahun_ajaran_id ?? TahunAjaran::where('is_active', true)->first()?->id;
        if ($taId) $query->where('tahun_ajaran_id', $taId);

        if ($request->filled('semester')) {
            $query->whereHas('tahunAjaran', fn($q) => $q->where('semester', $request->semester));
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
        return $libur || date('N', strtotime($date)) >= 6; // 6=Sabtu, 7=Minggu
    }
}