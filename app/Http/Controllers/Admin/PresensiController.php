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

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Presensi::class);

        if (!$request->filled(['kelas_id', 'bulan', 'tahun']) && !$request->filled('tanggal')) {
            return response()->json([
                'success' => false,
                'message' => 'Silakan pilih kelas dan filter waktu (bulan/tahun atau tanggal) terlebih dahulu.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $query = Presensi::query();
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

        if (!$request->filled('kelas_id')) {
            return response()->json([
                'success' => false, 
                'message' => 'Silakan pilih kelas terlebih dahulu.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $kelasId = (string) $request->kelas_id;
        $tanggal = $request->get('tanggal', date('Y-m-d'));
        $tahunAjaranId = $request->tahun_ajaran_id ?? TahunAjaran::where('is_active', true)->first()?->id;

        $siswa = Siswa::where('kelas_id', $kelasId)
            ->where('is_active', true)
            ->with(['kelas', 'presensi' => function ($q) use ($tanggal, $tahunAjaranId) {
                $q->whereDate('tanggal', $tanggal);
                if ($tahunAjaranId) $q->where('tahun_ajaran_id', $tahunAjaranId);
            }])
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
            return response()->json(['success' => false, 'message' => 'Tidak dapat input presensi di hari libur/akhir pekan.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $tahunAjaran = TahunAjaran::where('is_active', true)->firstOrFail();
            $dataInput = $request->has('data_presensi') ? $request->input('data_presensi') : [$request->all()];

            $results = DB::transaction(function () use ($dataInput, $tanggalInput, $user, $tahunAjaran) {
                $savedData = [];
                foreach ($dataInput as $item) {
                    if (!isset($item['siswa_id']) || !isset($item['status'])) continue;

                    $siswa = Siswa::findOrFail($item['siswa_id']);
                    
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
                'message' => 'Presensi berhasil disimpan oleh Admin.',
                'count' => count($results)
            ], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            Log::error('Admin Store Presensi Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
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
            return response()->json(['success' => true, 'data' => new PresensiResource($presensi->load(['siswa.kelas', 'guruStaf', 'tahunAjaran']))], Response::HTTP_OK);
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

        if (!$request->filled(['kelas_id', 'bulan', 'tahun'])) {
            return response()->json([
                'success' => false, 
                'message' => 'Silakan pilih kelas, bulan, dan tahun untuk mengekspor data.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $profil = DB::table('profil_sekolah')->first();
        $kontak = DB::table('data_kontak')->first();
        
        $month = $request->get('bulan');
        $year = $request->get('tahun');
        
        $query = Presensi::query();
        $filteredQuery = $this->applyPresensiFilters($request, $query);

        $taId = $request->get('tahun_ajaran_id') ?? TahunAjaran::where('is_active', true)->first()?->id;
        $taData = DB::table('tahun_ajaran')->where('id', $taId)->first();
        $taLabel = $taData ? $taData->nama . " (" . $taData->semester . ")" : '-';

        $dataKelas = Kelas::with('waliKelas')->find($request->kelas_id);
        $namaKelas = $dataKelas ? $dataKelas->nama_kelas : "Semua_Kelas";

        return Excel::download(
            new PresensiExport($filteredQuery, $namaKelas, "Bulan-$month-$year", $profil, $kontak, $dataKelas, 'kesiswaan', $taLabel), 
            "Rekap_Presensi_Kelas_{$namaKelas}_Bulan_{$month}_{$year}.xlsx"
        );
    }

    private function applyPresensiFilters(Request $request, $query)
    {
        $taId = $request->tahun_ajaran_id ?? TahunAjaran::where('is_active', true)->first()?->id;
        if ($taId) $query->where('tahun_ajaran_id', $taId);

        if ($request->filled('kelas_id')) {
            $query->whereHas('siswa', fn($q) => $q->where('kelas_id', (string) $request->get('kelas_id')));
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