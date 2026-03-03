<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\{GuruMapel, JamSekolah, Semester, ProfilSekolah, DataKontak, StrukturJabatan};
use App\Http\Resources\GuruMapelResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Log, Auth};
use Illuminate\Support\Str;
use Carbon\Carbon;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class GuruMapelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);
    }

    /**
     * Mendapatkan riwayat kelas siswa berdasarkan semester yang sedang aktif.
     */
    private function getRiwayatAktif()
    {
        $siswa = Auth::user()->siswa;
        if (!$siswa) return null;

        $semesterAktifId = Semester::where('is_active', true)->value('id');

        return $siswa->riwayatKelas()
            ->where('semester_id', $semesterAktifId)
            ->where('is_active', true)
            ->with(['kelas.waliKelas', 'semester.tahunAjaran'])
            ->first();
    }

    /**
     * Query dasar untuk mengambil jadwal pelajaran siswa.
     */
    private function applyBaseFilters(Request $request)
    {
        $riwayat = $this->getRiwayatAktif();
        
        $kelasId = $riwayat?->kelas_id;
        $semesterId = $riwayat?->semester_id ?? Semester::where('is_active', 1)->value('id');

        $query = GuruMapel::with(['guru', 'mapel.jurusan', 'kelas', 'semester.tahunAjaran', 'jamMulai', 'jamSelesai'])
            ->select('guru_mapel.*')
            ->leftJoin('jam_sekolah as jm', 'guru_mapel.jam_mulai_id', '=', 'jm.id')
            ->where('guru_mapel.kelas_id', $kelasId)
            ->where('guru_mapel.semester_id', $semesterId);

        $query->whereHas('mapel', function ($q) {
            $q->where('is_active', 1);
        });

        $query->when($request->tipe_mapel, function ($q, $tipe) {
            return $q->whereHas('mapel', fn($m) => $m->where('tipe_mapel', $tipe));
        });

        $query->when($request->kategori_mapel, function ($q, $kategori) {
            return $q->whereHas('mapel', fn($m) => $m->where('kategori_mapel', $kategori));
        });

        if ($request->filled('q')) {
            $search = $request->get('q');
            $query->where(function ($q) use ($search) {
                $q->whereHas('guru', fn($g) => $g->where('nama', 'LIKE', "%{$search}%"))
                  ->orWhereHas('mapel', fn($m) => $m->where('nama_mapel', 'LIKE', "%{$search}%"));
            });
        }

        $query->when($request->mata_pelajaran_id, fn($q, $id) => $q->where('mata_pelajaran_id', $id));

        return $query;
    }

    /**
     * Menampilkan daftar jadwal pelajaran (API).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', GuruMapel::class);
        
        $riwayat = $this->getRiwayatAktif();

        if (!$riwayat?->kelas_id) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal tidak tersedia karena Anda belum terdaftar di kelas untuk semester ini.',
                'data'    => []
            ], Response::HTTP_OK);
        }

        $query = $this->applyBaseFilters($request);

        if ($request->filled('hari')) {
            $query->where('guru_mapel.hari', $request->hari);
        } elseif (!$request->filled('q')) {
            $query->where('guru_mapel.hari', Carbon::now()->translatedFormat('l'));
        }

        $assignments = $query->orderByRaw("FIELD(guru_mapel.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu')")
                             ->orderByRaw("CASE WHEN guru_mapel.jam_mulai_id IS NULL THEN 1 ELSE 0 END ASC")
                             ->orderBy('jm.waktu_mulai', 'asc')
                             ->get();

        return response()->json([
            'success' => true,
            'message' => $assignments->isEmpty() ? 'Tidak ada jadwal pelajaran.' : 'Data jadwal berhasil diambil.',
            'header'  => [
                'nama'         => Auth::user()->siswa?->nama_lengkap,
                'kelas'        => $riwayat->kelas?->nama_kelas ?? 'Tanpa Kelas',
                'tahun_ajaran' => $riwayat->semester?->tahunAjaran?->nama ?? 'Tidak Diketahui',
                'semester'     => $riwayat->semester?->nama ?? '-',
                'hari_ini'     => Carbon::now()->translatedFormat('l, d F Y'),
            ],
            'data'    => GuruMapelResource::collection($assignments),
        ], Response::HTTP_OK);
    }

    /**
     * Export jadwal ke format PDF.
     */
    public function exportPdf(Request $request)
    {
        try {
            $this->authorize('export', GuruMapel::class);
            $query = $this->applyBaseFilters($request);

            $labelHari = $request->filled('hari') ? strtoupper($request->hari) : 'SEMUA HARI';
            if ($request->filled('hari')) {
                $query->where('guru_mapel.hari', $request->hari);
            }

            $data = $query->orderByRaw("FIELD(guru_mapel.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu')")
                          ->orderByRaw("CASE WHEN guru_mapel.jam_mulai_id IS NULL THEN 1 ELSE 0 END ASC")
                          ->orderBy('jm.waktu_mulai', 'asc')
                          ->get();

            $riwayat = $this->getRiwayatAktif();
            $profil = ProfilSekolah::first() ?? new ProfilSekolah();
            $kontak = DataKontak::first() ?? new DataKontak();
            
            $kepsekData = StrukturJabatan::where('jabatan_id', 1)->with('guruStaf')->first();
            $wakaKurData = StrukturJabatan::where('jabatan_id', 2)->with('guruStaf')->first();

            $payload = [
                'data'          => $data,
                'profil'        => $profil,
                'kontak'        => $kontak,
                'semester_nama' => strtoupper(($riwayat?->semester?->nama ?? '') . ' ' . ($riwayat?->semester?->tahunAjaran?->nama ?? '')),
                'kelas'         => $riwayat?->kelas?->nama_kelas ?? 'Kelas Siswa',
                'hari'          => $labelHari,
                'wali'          => $riwayat?->kelas?->waliKelas?->nama ?? '...........................',
                'nipWali'       => $riwayat?->kelas?->waliKelas?->nip ?? '...........................',
                'kepsek'        => $kepsekData?->guruStaf?->nama ?? $profil->nama_kepala_sekolah ?? '...........................',
                'nipKepsek'     => $kepsekData?->guruStaf?->nip ?? $profil->nip_kepala_sekolah ?? '...........................',
                'wakaKur'       => $wakaKurData?->guruStaf?->nama ?? '...........................',
                'nipWakaKur'    => $wakaKurData?->guruStaf?->nip ?? '...........................',
                'kategori'      => $request->get('kategori_mapel', 'Semua Kategori')
            ];

            if (ob_get_length()) ob_end_clean();

            $pdf = Pdf::loadView('exports.jadwal_mapel_kelas_pdf', $payload)
                      ->setPaper('a4', 'portrait');

            $fileName = 'JADWAL_SISWA_' . strtoupper(Str::slug($payload['kelas'], '_')) . '.pdf';
            return $pdf->download($fileName);

        } catch (Throwable $e) {
            Log::error('Export PDF Guru Mapel Error: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal membuat PDF.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Menampilkan detail satu jadwal pelajaran.
     */
    public function show(GuruMapel $guruMapel): JsonResponse
    {
        $this->authorize('view', $guruMapel);
        
        $riwayat = $this->getRiwayatAktif();
        if ($guruMapel->kelas_id !== $riwayat?->kelas_id) {
            return response()->json(['message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
        }

        $guruMapel->load(['guru', 'mapel.jurusan', 'kelas', 'semester.tahunAjaran', 'jamMulai', 'jamSelesai']);
        
        return response()->json([
            'success' => true,
            'data'    => new GuruMapelResource($guruMapel)
        ], Response::HTTP_OK);
    }

    /**
     * Mengambil daftar jam sekolah berdasarkan hari.
     */
    public function getJamByHari(Request $request): JsonResponse
    {
        $hari = $request->query('hari') ?? Carbon::now()->translatedFormat('l');
        $jam = JamSekolah::where('hari', $hari)->orderBy('waktu_mulai')->get();
        return response()->json(['success' => true, 'data' => $jam], Response::HTTP_OK);
    }

    /**
     * Method dilarang untuk akses Siswa.
     */
    public function store() { return response()->json(['message' => 'Akses dilarang.'], Response::HTTP_FORBIDDEN); }
    public function update() { return response()->json(['message' => 'Akses dilarang.'], Response::HTTP_FORBIDDEN); }
    public function destroy() { return response()->json(['message' => 'Akses dilarang.'], Response::HTTP_FORBIDDEN); }
}