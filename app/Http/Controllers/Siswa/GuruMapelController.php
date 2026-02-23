<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\{GuruMapel, JamSekolah, TahunAjaran, ProfilSekolah, DataKontak, StrukturJabatan};
use App\Http\Resources\GuruMapelResource;
use App\Exports\GuruMapelExport;
use Maatwebsite\Excel\Facades\Excel;
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

    private function getKelasIdSiswa()
    {
        return Auth::user()->siswa->kelas_id ?? null;
    }

    private function applyBaseFilters(Request $request)
    {
        $kelasId = $this->getKelasIdSiswa();
        $tahunAktif = TahunAjaran::where('is_active', 1)->first();

        $query = GuruMapel::with(['guru', 'mapel.jurusan', 'kelas', 'tahunAjaran', 'jamMulai', 'jamSelesai'])
            ->where('kelas_id', $kelasId)
            ->where('tahun_ajaran_id', $tahunAktif?->id);

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

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', GuruMapel::class);
        $query = $this->applyBaseFilters($request);

        if ($request->filled('hari')) {
            $query->where('hari', $request->hari);
        } elseif (!$request->filled('q')) {
            $query->where('hari', Carbon::now()->translatedFormat('l'));
        }

        $assignments = $query->orderByRaw("FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu')")
                             ->orderBy('jam_mulai_id')
                             ->get();

        return response()->json([
            'success' => true,
            'data'    => GuruMapelResource::collection($assignments),
        ], Response::HTTP_OK);
    }

    public function exportPdf(Request $request)
    {
        try {
            $this->authorize('export', GuruMapel::class);
            $query = $this->applyBaseFilters($request);

            $labelHari = 'SEMUA HARI';
            if ($request->filled('hari')) {
                $query->where('hari', $request->hari);
                $labelHari = strtoupper($request->hari);
            }

            $data = $query->orderByRaw("FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu')")
                          ->orderBy('jam_mulai_id')
                          ->get();

            $profil = ProfilSekolah::first() ?? new ProfilSekolah();
            $kontak = DataKontak::first() ?? new DataKontak();
            $tahunAktif = TahunAjaran::where('is_active', 1)->first();
            
            // 1. Data Kelas & Wali Kelas
            $siswa = Auth::user()->siswa;
            $kelas = $siswa->kelas->nama_kelas ?? 'Kelas Siswa';
            $wali = $siswa->kelas->waliKelas->nama ?? '...........................';
            $nipWali = $siswa->kelas->waliKelas->nip ?? '...........................';

            // 2. Data Kepala Sekolah (Otomatis ID 1)
            $kepsekData = StrukturJabatan::where('jabatan_id', 1)->with('guruStaf')->first();
            $kepsek = $kepsekData->guruStaf->nama ?? $profil->nama_kepala_sekolah ?? '...........................';
            $nipKepsek = $kepsekData->guruStaf->nip ?? $profil->nip_kepala_sekolah ?? '...........................';

            // 3. Data Wakasek Kurikulum (Otomatis ID 2)
            $wakaKurData = StrukturJabatan::where('jabatan_id', 2)->with('guruStaf')->first();
            $wakaKur = $wakaKurData->guruStaf->nama ?? '...........................';
            $nipWakaKur = $wakaKurData->guruStaf->nip ?? '...........................';
            
            $fileName = 'JADWAL_SISWA_' . strtoupper(Str::slug($kelas, '_')) . '.pdf';

            if (ob_get_contents()) ob_end_clean();

            $pdf = Pdf::loadView('exports.jadwal_mapel_kelas_pdf', [
                'data'       => $data,
                'profil'     => $profil,
                'kontak'     => $kontak,
                'tahun'      => $tahunAktif,
                'kelas'      => $kelas,
                'hari'       => $labelHari,
                'wali'       => $wali,
                'nipWali'    => $nipWali,
                'kepsek'     => $kepsek,
                'nipKepsek'  => $nipKepsek,
                'wakaKur'    => $wakaKur,
                'nipWakaKur' => $nipWakaKur,
                'kategori'   => $request->get('kategori_mapel', 'Semua Kategori')
            ]);

            // DIUBAH MENJADI PORTRAIT KARENA LANDSCAPE TERLALU BESAR
            $pdf->setPaper('a4', 'portrait'); 
            return $pdf->download($fileName);
        } catch (Throwable $e) {
            Log::error('Export PDF Guru Mapel Error: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal membuat PDF.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        try {
            $this->authorize('export', GuruMapel::class);
            $query = $this->applyBaseFilters($request);

            if ($request->filled('hari')) {
                $query->where('hari', $request->hari);
            }

            $tahunAktif = TahunAjaran::where('is_active', 1)->first();
            
            // Tambahkan data tanda tangan ke dalam filters agar terbaca di Excel/PDF Export class
            $siswa = Auth::user()->siswa;
            $kepsekData = StrukturJabatan::where('jabatan_id', 1)->with('guruStaf')->first();
            $wakaKurData = StrukturJabatan::where('jabatan_id', 2)->with('guruStaf')->first();

            $filters = [
                'q'              => $request->get('q'),
                'hari'           => $request->filled('hari') ? $request->hari : 'SEMUA HARI',
                'tahun_ajaran'   => $tahunAktif?->nama ?? 'Semua',
                'semester'       => $tahunAktif?->semester ?? 'Semua', 
                'kelas'          => $siswa->kelas->nama_kelas ?? 'Kelas Siswa',
                'tipe_mapel'     => $request->get('tipe_mapel', 'Semua Tipe'),
                'kategori_mapel' => $request->get('kategori_mapel', 'Semua Kategori'),
                // Data Tanda Tangan untuk dikirim ke Export Class
                'wali'           => $siswa->kelas->waliKelas->nama ?? '...........................',
                'nipWali'        => $siswa->kelas->waliKelas->nip ?? '...........................',
                'kepsek'         => $kepsekData->guruStaf->nama ?? '...........................',
                'nipKepsek'      => $kepsekData->guruStaf->nip ?? '...........................',
                'wakaKur'        => $wakaKurData->guruStaf->nama ?? '...........................',
                'nipWakaKur'     => $wakaKurData->guruStaf->nip ?? '...........................',
            ];

            $profil = ProfilSekolah::first() ?? new ProfilSekolah();
            $kontak = DataKontak::first() ?? new DataKontak();
            $fileName = 'JADWAL_SISWA_' . strtoupper(Str::slug($filters['kelas'], '_')) . '.xlsx';

            if (ob_get_contents()) ob_end_clean();
            return Excel::download(new GuruMapelExport($query, $profil, $kontak, $filters), $fileName);
        } catch (Throwable $e) {
            Log::error('Export Excel Guru Mapel Error: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal mengekspor data Excel.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(GuruMapel $guruMapel): JsonResponse
    {
        $this->authorize('view', $guruMapel);
        
        if ($guruMapel->kelas_id !== $this->getKelasIdSiswa()) {
            return response()->json(['message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'success' => true,
            'data'    => new GuruMapelResource($guruMapel->load(['guru', 'mapel.jurusan', 'kelas', 'tahunAjaran', 'jamMulai', 'jamSelesai']))
        ], Response::HTTP_OK);
    }

    public function getJamByHari(Request $request): JsonResponse
    {
        $hari = $request->query('hari') ?? Carbon::now()->translatedFormat('l');
        $jam = JamSekolah::where('hari', $hari)->orderBy('waktu_mulai')->get();
        return response()->json(['success' => true, 'data' => $jam], Response::HTTP_OK);
    }

    public function store() { return response()->json(['message' => 'Akses dilarang.'], Response::HTTP_FORBIDDEN); }
    public function update() { return response()->json(['message' => 'Akses dilarang.'], Response::HTTP_FORBIDDEN); }
    public function destroy() { return response()->json(['message' => 'Akses dilarang.'], Response::HTTP_FORBIDDEN); }
}