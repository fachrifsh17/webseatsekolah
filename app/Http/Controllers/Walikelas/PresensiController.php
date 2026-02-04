<?php

namespace App\Http\Controllers\Walikelas;

use App\Http\Controllers\Controller;
use App\Models\{Presensi, Siswa, Kelas, TahunAjaran};
use App\Http\Resources\PresensiResource;
use App\Exports\PresensiExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Log};
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PresensiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Guru');
        $this->middleware('log.admin')->only(['store']);
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $taActive = TahunAjaran::where('is_active', true)->first();

            if (!$taActive) {
                return response()->json(['success' => false, 'message' => 'Tahun ajaran aktif tidak ditemukan.'], Response::HTTP_NOT_FOUND);
            }

            if (!$request->filled(['bulan', 'tahun']) && !$request->filled('tanggal')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Silakan pilih filter waktu (bulan/tahun atau tanggal) terlebih dahulu.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $user = Auth::user();
            $guruStafId = $user->guruStaf?->id;

            if (!$guruStafId) {
                return response()->json(['success' => false, 'message' => 'Data guru tidak ditemukan.'], Response::HTTP_FORBIDDEN);
            }

            $kelas = Kelas::where('wali_kelas_id', $guruStafId)->where('is_active', true)->first();

            if (!$kelas) {
                return response()->json(['success' => false, 'message' => 'Anda tidak memiliki kelas perwalian aktif.'], Response::HTTP_FORBIDDEN);
            }

            $query = Presensi::with(['siswa', 'tahunAjaran'])
                ->where('tahun_ajaran_id', $taActive->id)
                ->whereHas('siswa', function ($q) use ($kelas) {
                    $q->where('kelas_id', $kelas->id);
                });

            if ($request->filled('tanggal')) {
                $query->whereDate('tanggal', $request->tanggal);
            }

            if ($request->filled('bulan') && $request->filled('tahun')) {
                $query->whereMonth('tanggal', $request->bulan)
                      ->whereYear('tanggal', $request->tahun);
            }

            if ($request->filled('search')) {
                $query->whereHas('siswa', function ($q) use ($request) {
                    $q->where('nama_lengkap', 'like', "%{$request->search}%");
                });
            }

            $perPage = min((int) $request->get('per_page', 20), 100);
            $data = $query->orderBy('tanggal', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Riwayat presensi kelas ' . $kelas->nama_kelas . ' berhasil diambil.',
                'data' => PresensiResource::collection($data)->response()->getData(true)
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Walikelas Presensi Index Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengambil data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function siswaWali(Request $request): JsonResponse
    {
        $user = Auth::user();
        $guruStafId = $user->guruStaf?->id;
        $kelas = Kelas::where('wali_kelas_id', $guruStafId)->where('is_active', true)->first();

        if (!$kelas) {
            return response()->json(['success' => false, 'message' => 'Kelas perwalian tidak ditemukan.'], Response::HTTP_FORBIDDEN);
        }

        $tanggal = $request->get('tanggal', date('Y-m-d'));

        $summary = Presensi::whereHas('siswa', fn($q) => $q->where('kelas_id', $kelas->id))
            ->whereDate('tanggal', $tanggal)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $siswaList = Siswa::where('kelas_id', $kelas->id)
            ->where('is_active', true)
            ->with(['presensi' => function ($q) use ($tanggal) {
                $q->whereDate('tanggal', $tanggal);
            }])
            ->when($request->search, fn($q) => $q->where('nama_lengkap', 'like', "%{$request->search}%"))
            ->orderBy('nama_lengkap', 'asc')
            ->get();

        $collection = $siswaList->map(function ($s) use ($tanggal) {
            $p = $s->presensi->first() ?: new Presensi([
                'siswa_id' => (string)$s->id, 
                'tanggal' => $tanggal, 
                'status' => null
            ]);
            $p->setRelation('siswa', $s);
            return new PresensiResource($p);
        });

        return response()->json([
            'success' => true,
            'nama_kelas' => $kelas->nama_kelas,
            'summary' => [
                'hadir' => $summary['Hadir'] ?? 0,
                'sakit' => $summary['Sakit'] ?? 0,
                'izin'  => $summary['Izin'] ?? 0,
                'alpa'  => $summary['Alpa'] ?? 0,
            ],
            'data' => $collection
        ], Response::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        $sekarang = now('Asia/Jakarta');
        $jamMenit = $sekarang->format('H:i');
        $tgl = date('Y-m-d');
        
        if ($this->isDayOff($tgl)) {
            return response()->json(['success' => false, 'message' => 'Hari libur tidak dapat input presensi.'], Response::HTTP_FORBIDDEN);
        }

        if ($jamMenit < '06:30' || $jamMenit > '12:00') {
            return response()->json(['success' => false, 'message' => 'Input presensi hanya dilayani jam 06:30 - 12:00.'], Response::HTTP_FORBIDDEN);
        }

        $user = Auth::user();
        $guruStafId = $user->guruStaf?->id;
        $kelas = Kelas::where('wali_kelas_id', $guruStafId)->where('is_active', true)->first();
        
        if (!$kelas) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
        }

        $ta = TahunAjaran::where('is_active', true)->firstOrFail();

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.siswa_id' => 'required|exists:siswa,id',
            'items.*.status' => 'required|in:Hadir,Sakit,Izin,Alpa',
            'items.*.keterangan' => 'nullable|string'
        ]);

        try {
            DB::transaction(function () use ($validated, $tgl, $guruStafId, $ta) {
                foreach ($validated['items'] as $item) {
                    Presensi::updateOrCreate(
                        ['siswa_id' => $item['siswa_id'], 'tanggal' => $tgl],
                        [
                            'tahun_ajaran_id' => $ta->id,
                            'status' => $item['status'], 
                            'keterangan' => $item['keterangan'] ?? 'Diinput oleh Wali Kelas', 
                            'guru_staf_id' => $guruStafId
                        ]
                    );
                }
            });
            return response()->json(['success' => true, 'message' => 'Presensi berhasil disimpan.'], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Walikelas Store Presensi Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        $taActive = TahunAjaran::where('is_active', true)->first();

        if (!$taActive) {
            return response()->json(['success' => false, 'message' => 'Tahun ajaran aktif tidak ditemukan.'], Response::HTTP_NOT_FOUND);
        }

        if (!$request->filled(['bulan', 'tahun'])) {
            return response()->json([
                'success' => false, 
                'message' => 'Silakan pilih bulan dan tahun untuk mengekspor data.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = Auth::user();
        $guruStafId = $user->guruStaf?->id;
        $kelas = Kelas::where('wali_kelas_id', $guruStafId)->where('is_active', true)->first();

        if (!$kelas) {
            return response()->json(['success' => false, 'message' => 'Data tidak tersedia.'], Response::HTTP_FORBIDDEN);
        }

        $month = $request->get('bulan');
        $year = $request->get('tahun');

        $query = Presensi::query()
            ->where('tahun_ajaran_id', $taActive->id)
            ->whereHas('siswa', fn($q) => $q->where('kelas_id', $kelas->id))
            ->whereMonth('tanggal', $month)
            ->whereYear('tanggal', $year);

        $taLabel = $taActive->nama . " (" . $taActive->semester . ")";
        $profil = DB::table('sekolah_setting')->first();
        $kontak = DB::table('data_kontak')->first();
        $namaKelas = $kelas->nama_kelas;

        return Excel::download(
            new PresensiExport($query->orderBy('tanggal', 'asc'), $namaKelas, "Bulan-$month-$year", $profil, $kontak, $kelas, 'walikelas', $taLabel), 
            "Rekap_Presensi_Walikelas_Kelas_{$namaKelas}_Bulan_{$month}_{$year}.xlsx"
        );
    }

    private function isDayOff($date): bool
    {
        $libur = DB::table('kalender_akademik')->where('kategori', 'Libur')
            ->whereDate('tanggal_mulai', '<=', $date)
            ->whereDate('tanggal_selesai', '>=', $date)->first();
        return $libur || date('N', strtotime($date)) >= 6;
    }
}