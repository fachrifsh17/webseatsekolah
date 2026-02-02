<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Presensi;
use App\Models\Siswa;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Http\Resources\PresensiResource;
use App\Exports\PresensiExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PresensiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Guru');
    }

    public function siswaWali(Request $request): JsonResponse
    {
        $user = Auth::user();
        $guruStafId = $user->guruStaf?->id;
        $semester = $request->semester;

        if (!$guruStafId) {
            return response()->json(['success' => false, 'message' => 'Data guru tidak ditemukan.'], Response::HTTP_FORBIDDEN);
        }

        if ($request->filled('tahun_ajaran_id')) {
            $tahunAjaranId = $request->tahun_ajaran_id;
        } else {
            $tahunAktif = TahunAjaran::where('is_active', true)->first();
            $tahunAjaranId = $tahunAktif?->id;
        }

        $kelas = Kelas::where('wali_kelas_id', $guruStafId)
            ->where('is_active', true)
            ->first();

        if (!$kelas) {
            return response()->json(['success' => false, 'message' => 'Kelas perwalian tidak ditemukan atau sudah tidak aktif.'], Response::HTTP_FORBIDDEN);
        }

        $tanggal = $request->get('tanggal', date('Y-m-d'));

        $summary_query = Presensi::whereHas('siswa', function($q) use ($kelas) {
                $q->where('kelas_id', $kelas->id);
            })
            ->whereDate('tanggal', $tanggal)
            ->when($tahunAjaranId, fn($q) => $q->where('tahun_ajaran_id', $tahunAjaranId))
            ->when($semester, fn($q) => $q->whereHas('tahunAjaran', fn($sq) => $sq->where('semester', $semester)));

        $summary_global = $summary_query->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status');

        $siswa = Siswa::where('kelas_id', $kelas->id)
            ->where('is_active', true)
            ->with(['presensi' => function($q) use ($tanggal, $tahunAjaranId, $semester) {
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
            $presensi = $item->presensi->first() ?? new Presensi([
                'siswa_id' => (string) $item->id,
                'tanggal'  => $tanggal,
                'status'   => null
            ]);
            $presensi->setRelation('siswa', $item);
            return new PresensiResource($presensi);
        });

        return response()->json([
            'success' => true,
            'nama_kelas' => $kelas->nama_kelas,
            'summary_global' => [
                'hadir' => $summary_global['Hadir'] ?? 0,
                'sakit' => $summary_global['Sakit'] ?? 0,
                'izin'  => $summary_global['Izin'] ?? 0,
                'alpa'  => $summary_global['Alpa'] ?? 0,
            ],
            'data' => $collection
        ], Response::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        $jamSekarang = now('Asia/Jakarta')->format('H:i');
        if ($jamSekarang < '07:00' || $jamSekarang > '23:59') {
             return response()->json(['success' => false, 'message' => 'Input hanya diperbolehkan pada jam operasional.'], Response::HTTP_FORBIDDEN);
        }

        $tanggal = date('Y-m-d');
        if ($this->isDayOff($tanggal)) {
            return response()->json(['success' => false, 'message' => 'Hari libur.'], Response::HTTP_BAD_REQUEST);
        }

        $user = Auth::user();
        $guruStafId = $user->guruStaf?->id;

        $kelasWali = Kelas::where('wali_kelas_id', $guruStafId)
            ->where('is_active', true)
            ->first();

        if (!$kelasWali) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak. Anda bukan Wali Kelas aktif.'], Response::HTTP_FORBIDDEN);
        }

        $tahunAjaran = TahunAjaran::where('is_active', true)->firstOrFail();

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.siswa_id' => 'required|exists:siswa,id',
            'items.*.status' => 'required|in:Hadir,Sakit,Izin,Alpa',
            'items.*.keterangan' => 'nullable|string'
        ]);

        $siswaIds = collect($validated['items'])->pluck('siswa_id')->toArray();
        $jumlahSiswaBukanWali = Siswa::whereIn('id', $siswaIds)
            ->where('kelas_id', '!=', $kelasWali->id)
            ->count();

        if ($jumlahSiswaBukanWali > 0) {
            return response()->json([
                'success' => false, 
                'message' => 'Akses ditolak. Salah satu siswa bukan anggota kelas Anda.'
            ], Response::HTTP_FORBIDDEN);
        }

        DB::transaction(function () use ($validated, $tanggal, $guruStafId, $tahunAjaran) {
            foreach ($validated['items'] as $item) {
                Presensi::updateOrCreate(
                    [
                        'siswa_id' => $item['siswa_id'], 
                        'tanggal' => $tanggal,
                        'tahun_ajaran_id' => $tahunAjaran->id
                    ],
                    [
                        'status' => $item['status'],
                        'keterangan' => $item['keterangan'] ?? 'Input oleh Wali Kelas',
                        'guru_staf_id' => $guruStafId,
                    ]
                );
            }
        });

        return response()->json(['success' => true, 'message' => 'Berhasil disimpan.'], Response::HTTP_OK);
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        $semester = $request->semester;
        
        $taAktif = TahunAjaran::where('is_active', true)->first();
        $tahunAjaranId = $request->get('tahun_ajaran_id') ?? $taAktif?->id;

        $kelas = Kelas::with('waliKelas')
            ->where('wali_kelas_id', $user->guruStaf?->id)
            ->where('is_active', true)
            ->first();

        if (!$kelas) {
            return response()->json(['success' => false, 'message' => 'Data tidak tersedia.'], Response::HTTP_FORBIDDEN);
        }

        $taData = DB::table('tahun_ajaran')->where('id', $tahunAjaranId)->first();
        $tahunAjaranLabel = $taData ? $taData->nama . " (" . $taData->semester . ")" : '-';

        $profil = DB::table('profil_sekolah')->first();
        $kontak = DB::table('data_kontak')->first();

        $bulan = $request->get('bulan', date('m'));
        $tahun = $request->get('tahun', date('Y'));
        $labelWaktu = "Bulan-{$bulan}-{$tahun}";

        $query = Presensi::with(['siswa.kelas', 'tahunAjaran', 'guruStaf'])
            ->join('siswa', 'presensi.siswa_id', '=', 'siswa.id')
            ->where('siswa.kelas_id', $kelas->id) 
            ->where('presensi.tahun_ajaran_id', $tahunAjaranId)
            ->when($semester, fn($q) => $q->whereHas('tahunAjaran', fn($sq) => $sq->where('semester', $semester)))
            ->whereMonth('presensi.tanggal', $bulan)
            ->whereYear('presensi.tanggal', $tahun)
            ->select('presensi.*')
            ->orderBy('presensi.tanggal', 'asc');

        $fileName = 'Presensi_' . str_replace(' ', '_', $kelas->nama_kelas) . '_' . $labelWaktu . '.xlsx';

        return Excel::download(
            new PresensiExport(
                $query, 
                $kelas->nama_kelas, 
                $labelWaktu, 
                $profil, 
                $kontak, 
                $kelas, 
                'walikelas',
                $tahunAjaranLabel
            ), 
            $fileName
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