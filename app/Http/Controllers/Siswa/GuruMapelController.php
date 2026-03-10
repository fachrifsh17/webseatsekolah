<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\{GuruMapel, JamSekolah, Semester, ProfilSekolah, DataKontak, StrukturJabatan};
use App\Http\Resources\GuruMapelResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Log, Auth, DB};
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

    private function getRiwayatAktif()
    {
        $siswa = Auth::user()->siswa;
        if (!$siswa) return null;

        $semesterAktifId = Semester::where('is_active', true)->value('id');

        return $siswa->riwayatKelas()
            ->where('semester_id', $semesterAktifId)
            ->where('is_active', true)
            ->with(['kelas', 'semester.tahunAjaran'])
            ->first();
    }

    private function applyBaseFilters(Request $request)
    {
        $riwayat = $this->getRiwayatAktif();
        $kelasId = $riwayat?->kelas_id;
        // Semester ID dikunci dari riwayat aktif, fallback ke semester is_active=1
        $semesterId = $riwayat?->semester_id ?? Semester::where('is_active', 1)->value('id');

        $query = GuruMapel::with(['guru', 'mapel.jurusan', 'kelas', 'semester.tahunAjaran', 'jamMulai', 'jamSelesai'])
            ->where('kelas_id', $kelasId)
            ->where('semester_id', $semesterId)
            ->where('is_active', 1);

        // Filter relasi yang harus aktif
        $query->whereHas('mapel', fn($q) => $q->where('is_active', 1));
        $query->whereHas('guru', fn($q) => $q->where('is_active', 1));
        $query->whereHas('kelas', fn($q) => $q->where('is_active', 1));

        // Filter Kategori Mapel
        $query->when($request->filled('kategori_mapel'), function ($q) use ($request) {
            return $q->whereHas('mapel', fn($m) => $m->where('kategori_mapel', $request->kategori_mapel));
        });

        // Pencarian global
        if ($request->filled('q')) {
            $search = $request->query('q');
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
        $riwayat = $this->getRiwayatAktif();

        if (!$riwayat?->kelas_id) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal tidak tersedia karena Anda belum terdaftar di kelas untuk semester ini.',
                'data'    => []
            ], Response::HTTP_OK);
        }

        $query = $this->applyBaseFilters($request);

        // Filter Hari
        if ($request->filled('hari')) {
            $query->where('hari', $request->hari);
        } elseif (!$request->filled('q')) {
            $query->where('hari', Carbon::now()->translatedFormat('l'));
        }

        $assignments = $query->orderBy(DB::raw("FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu')"))
                             ->orderByRaw("CASE WHEN jam_mulai_id IS NULL THEN 1 ELSE 0 END ASC")
                             ->orderBy('jam_mulai_id', 'asc')
                             ->get();

        return response()->json([
            'success' => true,
            'message' => $assignments->isEmpty() ? 'Tidak ada jadwal pelajaran.' : 'Data jadwal berhasil diambil.',
            'header'  => [
                'nama'         => Auth::user()->siswa?->nama_lengkap,
                'kelas'        => $riwayat->kelas?->nama_kelas ?? 'Tanpa Kelas',
                'tahun_ajaran' => $riwayat->semester?->tahunAjaran?->nama ?? 'Tidak Diketahui', // Terpisah
                'semester'     => $riwayat->semester?->nama ?? '-', // Terpisah
                'hari_ini'     => Carbon::now()->translatedFormat('l, d F Y'),
            ],
            'data'    => GuruMapelResource::collection($assignments),
        ], Response::HTTP_OK);
    }

    public function exportPdf(Request $request)
    {
        try {
            $this->authorize('export', GuruMapel::class);
            $query = $this->applyBaseFilters($request);

            $labelHari = $request->filled('hari') ? strtoupper($request->hari) : 'SEMUA HARI';
            if ($request->filled('hari')) {
                $query->where('hari', $request->hari);
            }

            $data = $query->orderBy(DB::raw("FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu')"))
                          ->orderByRaw("CASE WHEN jam_mulai_id IS NULL THEN 1 ELSE 0 END ASC")
                          ->orderBy('jam_mulai_id', 'asc')
                          ->get();

            $riwayat = $this->getRiwayatAktif();
            $profil = ProfilSekolah::first() ?? new ProfilSekolah();
            $kontak = DataKontak::first() ?? new DataKontak();
            
            $kepsekData = StrukturJabatan::where('jabatan_id', 1)->with('guruStaf')->first();
            $wakaKurData = StrukturJabatan::where('jabatan_id', 2)->with('guruStaf')->first();

            // Logika Status Aktif
            $statusLabel = 'TIDAK AKTIF';
            if ($data->isNotEmpty()) {
                $statusLabel = $data->first()->is_active == 1 ? 'AKTIF' : 'TIDAK AKTIF';
            } else {
                $statusCheck = GuruMapel::where('kelas_id', $riwayat?->kelas_id)->where('is_active', 1)->exists();
                $statusLabel = $statusCheck ? 'AKTIF' : 'TIDAK AKTIF';
            }

            $payload = [
                'data'          => $data,
                'profil'        => $profil,
                'kontak'        => $kontak,
                // Variabel TA dan Semester dipisah agar fleksibel di Blade
                'semester'      => strtoupper($riwayat?->semester?->nama ?? '-'),
                'tahun_ajaran'  => strtoupper($riwayat?->semester?->tahunAjaran?->nama ?? '-'),
                'kelas'         => $riwayat?->kelas?->nama_kelas ?? 'Kelas Siswa',
                'hari'          => $labelHari,
                'kepsek'        => $kepsekData?->guruStaf?->nama ?? $profil->nama_kepala_sekolah ?? '...........................',
                'nipKepsek'     => $kepsekData?->guruStaf?->nip ?? $profil->nip_kepala_sekolah ?? '...........................',
                'fileTtdKepsek' => $kepsekData?->file_ttd ?? $profil->ttd_kepala_sekolah ?? null, 
                'wakaKur'       => $wakaKurData?->guruStaf?->nama ?? '...........................',
                'nipWakaKur'    => $wakaKurData?->guruStaf?->nip ?? '...........................',
                'fileTtdWaka'   => $wakaKurData?->file_ttd ?? $profil->ttd_waka_kurikulum ?? null, 
                'kategori'      => $request->filled('kategori_mapel') ? strtoupper($request->kategori_mapel) : 'SEMUA KATEGORI',
                'status_aktif'  => $statusLabel
            ];

            if (ob_get_length()) ob_end_clean();

            $pdf = Pdf::loadView('exports.jadwal_mapel_kelas_pdf', $payload)
                      ->setPaper('a4', 'portrait');

            return $pdf->download('JADWAL_SISWA_' . strtoupper(Str::slug($payload['kelas'], '_')) . '.pdf');

        } catch (Throwable $e) {
            Log::error('Export PDF Guru Mapel Error: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal membuat PDF.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(GuruMapel $guruMapel): JsonResponse
    {
        $this->authorize('view', $guruMapel);
        $riwayat = $this->getRiwayatAktif();
        
        if ($guruMapel->kelas_id !== $riwayat?->kelas_id) {
            return response()->json(['message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
        }

        $guruMapel->load(['guru', 'mapel.jurusan', 'kelas', 'semester.tahunAjaran', 'jamMulai', 'jamSelesai']);
        return response()->json(['success' => true, 'data' => new GuruMapelResource($guruMapel)], Response::HTTP_OK);
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