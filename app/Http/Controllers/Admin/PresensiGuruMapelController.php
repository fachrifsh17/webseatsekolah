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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;
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
        if (!$user) return false;
        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('admin') || $user->hasRole('Admin');
        }
        return isset($user->role_id) && (int) $user->role_id === 2;
    }

    protected function applyGuruMapelOwnershipQuery($query, $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->whereHas('guruStaf', fn($r) => $r->where('user_id', $user->id))
              ->orWhereHas('guru', fn($r) => $r->where('user_id', $user->id));
            
            $candidates = ['guru_staf_id', 'guru_id', 'guru_staff_id'];
            foreach ($candidates as $col) {
                if (Schema::hasColumn('guru_mapel', $col)) {
                    $q->orWhere($col, $user->guruStaf?->id ?? $user->id);
                }
            }
        });
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PresensiGuruMapel::class);
        $user = Auth::user();

        $query = PresensiGuruMapel::with([
            'presensiSiswaDetail.siswa',
            'guruMapel.guru',
            'mataPelajaran',
            'kelas'
        ]);

        if (!$this->isAdmin($user)) {
            $query->whereHas('guruMapel', fn($q) => $this->applyGuruMapelOwnershipQuery($q, $user));
        }

        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
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
                'total' => $paginated->total(),
            ]
        ], Response::HTTP_OK);
    }

    public function listJadwalHariIni(): JsonResponse
    {
        $hariIni = Carbon::now()->locale('id')->dayName;
        $user = Auth::user();

        $query = GuruMapel::with(['mapel', 'kelas', 'guru'])
            ->where('hari', $hariIni);

        if (!$this->isAdmin($user)) {
            $this->applyGuruMapelOwnershipQuery($query, $user);
        }

        $jadwal = $query->get();
        $sudahAbsen = PresensiGuruMapel::whereDate('tanggal', Carbon::today())
            ->pluck('guru_mapel_id')->toArray();

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

    public function store(StorePresensiGuruMapelRequest $request): JsonResponse
    {
        $this->authorize('create', PresensiGuruMapel::class);
        $user = Auth::user();
        
        $relasi = GuruMapel::findOrFail($request->input('guru_mapel_id'));

        if (!$this->isAdmin($user)) {
            $check = GuruMapel::where('id', $relasi->id);
            $this->applyGuruMapelOwnershipQuery($check, $user);
            if (!$check->exists()) {
                return response()->json(['message' => 'Akses ditolak. Ini bukan jadwal Anda.'], 403);
            }
        }

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
                $semuaSiswaIds = Siswa::where('kelas_id', $relasi->kelas_id)->pluck('id');

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
            Log::error('Store Jurnal Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal simpan.'], 500);
        }
    }

    public function update(UpdatePresensiGuruMapelRequest $request, int $id): JsonResponse
    {
        $presensi = PresensiGuruMapel::findOrFail($id);
        $this->authorize('update', $presensi);
        $user = Auth::user();

        if (!$this->isAdmin($user)) {
            $check = GuruMapel::where('id', $presensi->guru_mapel_id);
            $this->applyGuruMapelOwnershipQuery($check, $user);
            if (!$check->exists()) {
                return response()->json(['message' => 'Akses ditolak. Jurnal ini bukan milik Anda.'], 403);
            }
        }

        try {
            $updated = DB::transaction(function () use ($request, $presensi) {
                $presensi->update($request->only(['materi', 'jam_masuk', 'jam_keluar', 'tanggal']));

                if ($request->has('presensi')) {
                    foreach ($request->input('presensi') as $item) {
                        $presensi->presensiSiswaDetail()->where('siswa_id', $item['siswa_id'])
                            ->update(['status' => $item['status'], 'catatan' => $item['catatan'] ?? null]);
                    }
                }
                return $presensi->load(['presensiSiswaDetail.siswa', 'guruMapel.guru', 'mataPelajaran', 'kelas']);
            });

            return response()->json(['success' => true, 'data' => new PresensiGuruMapelResource($updated)]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $presensi = PresensiGuruMapel::findOrFail($id);
        $this->authorize('delete', $presensi);
        $presensi->delete();

        return response()->json(['success' => true, 'message' => 'Jurnal berhasil dihapus.']);
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', PresensiGuruMapel::class);
        
        $month = $request->query('month', date('m'));
        $year = $request->query('year', date('Y'));
        $guruStafId = $request->query('guru_staf_id');
        $taAktif = DB::table('tahun_ajaran')->where('is_active', 1)->first();
        $tahunAjaranId = $request->query('tahun_ajaran_id') ?? $taAktif?->id;

        $query = PresensiGuruMapel::query()
            ->whereMonth('tanggal', $month)
            ->whereYear('tanggal', $year);

        if ($guruStafId) {
            $query->whereHas('guruMapel', fn($q) => $q->where('guru_staf_id', $guruStafId));
        }

        $taData = DB::table('tahun_ajaran')->where('id', $tahunAjaranId)->first();
        $guruTarget = DB::table('guru_staf')->where('id', $guruStafId)->first();

        return Excel::download(
            new PresensiGuruMapelExport(
                $query, "Bulan-{$month}-{$year}", 
                DB::table('profil_sekolah')->first(), 
                DB::table('data_kontak')->first(),
                (object)['nama' => $guruTarget->nama ?? 'Semua Guru', 'nip' => $guruTarget->nip ?? '-'],
                $taData ? $taData->nama . " (" . $taData->semester . ")" : '-',
                $request->filled('kelas_id')
            ),
            "Rekap_Jurnal_" . ($guruTarget->nama ?? 'Semua') . "_{$month}_{$year}.xlsx"
        );
    }
}