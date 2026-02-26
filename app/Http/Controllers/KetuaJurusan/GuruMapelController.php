<?php

namespace App\Http\Controllers\KetuaJurusan;

use App\Http\Controllers\Controller;
use App\Models\{GuruMapel, JamSekolah, TahunAjaran, GuruStaf, MataPelajaran, Kelas, ProfilSekolah, DataKontak};
use App\Http\Resources\GuruMapelResource;
use App\Exports\GuruMapelExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{DB, Log, Auth};
use Illuminate\Support\Str;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class GuruMapelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
    }

    private function getJurusanId()
    {
        return Auth::user()->guruStaf->jurusan_id ?? null;
    }

    private function getNamaJurusan()
    {
        return Auth::user()->guruStaf->jurusan->nama_jurusan ?? 'Jurusan';
    }

    private function applyFilters(Request $request)
    {
        $jurusanId = $this->getJurusanId();
        $tahunAktif = TahunAjaran::where('is_active', 1)->first();
        $tahunAjaranId = $request->get('tahun_ajaran_id', $tahunAktif?->id);

        $query = GuruMapel::with(['guru', 'mapel.jurusan', 'kelas', 'tahunAjaran', 'jamMulai', 'jamSelesai'])
            ->whereHas('kelas', function ($q) use ($jurusanId, $tahunAjaranId) {
                $q->where('jurusan_id', $jurusanId)
                  ->where('tahun_ajaran_id', $tahunAjaranId)
                  ->where('is_active', 1);
            });

        if (!$request->has('show_all')) {
            $query->whereHas('mapel', function ($q) {
                $q->where('is_active', 1);
            });
            
            $query->whereHas('guru', function ($q) {
                $q->where('is_active', 1);
            });
        }

        $query->when($request->tipe_mapel, function ($q, $tipe) {
            return $q->whereHas('mapel', fn($m) => $m->where('tipe_mapel', $tipe));
        });

        if ($request->filled('q')) {
            $search = $request->get('q');
            $query->where(function ($q) use ($search) {
                $q->whereHas('guru', fn($g) => $g->where('nama', 'LIKE', "%{$search}%")->orWhere('nip', 'LIKE', "%{$search}%"))
                  ->orWhereHas('mapel', fn($m) => $m->where('nama_mapel', 'LIKE', "%{$search}%"))
                  ->orWhereHas('kelas', fn($k) => $k->where('nama_kelas', 'LIKE', "%{$search}%"));
            });
        }

        $query->when($tahunAjaranId, fn($q) => $q->where('tahun_ajaran_id', $tahunAjaranId))
              ->when($request->guru_staf_id, fn($q, $id) => $q->where('guru_staf_id', $id))
              ->when($request->mata_pelajaran_id, fn($q, $id) => $q->where('mata_pelajaran_id', $id))
              ->when($request->kelas_id, fn($q, $id) => $q->where('kelas_id', $id))
              ->when($request->hari, fn($q, $hari) => $q->where('hari', $hari));

        return $query->orderBy(DB::raw("FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu')"))
                     ->orderByRaw("CASE WHEN jam_mulai_id IS NULL THEN 1 ELSE 0 END ASC")
                     ->orderBy('jam_mulai_id');
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->applyFilters($request);
        $perPage = (int) $request->get('per_page', 10);
        $assignments = $query->paginate($perPage);
        $paginationData = $assignments->toArray();
        
        return response()->json([
            'success' => true,
            'data'    => GuruMapelResource::collection($assignments),
            'meta'    => [
                'current_page'  => $assignments->currentPage(),
                'last_page'     => $assignments->lastPage(),
                'per_page'      => $assignments->perPage(),
                'total'         => $assignments->total(),
                'from'          => $assignments->firstItem(),
                'to'            => $assignments->lastItem(),
                'next_page_url' => $assignments->nextPageUrl(),
                'prev_page_url' => $assignments->previousPageUrl(),
                'path'          => $paginationData['path'] ?? null,
                'links'         => $paginationData['links'] ?? [],
            ],
        ], Response::HTTP_OK);
    }

    public function export(Request $request)
    {
        try {
            $query = $this->applyFilters($request);
            $namaJurusan = $this->getNamaJurusan();
            $tahunAktif = TahunAjaran::where('is_active', 1)->first();
            
            $nameParts = ['JADWAL_GURU_MAPEL'];
            $kriteria = ["JURUSAN " . strtoupper($namaJurusan)];

            $filters = [
                'q'                 => $request->get('q'),
                'hari'              => $request->get('hari'),
                'tahun_ajaran'      => 'Semua',
                'semester'          => 'Semua', 
                'guru'              => 'Semua Guru',
                'mapel'             => 'Semua Mapel',
                'kelas'             => 'Semua Kelas',
                'tipe_mapel'        => $request->get('tipe_mapel', 'Semua Tipe'),
                'status_mapel'      => $request->has('show_all') ? 'Semua (Aktif & Non-Aktif)' : 'Aktif',
                'identitas_laporan' => '' 
            ];

            $tahun = $request->filled('tahun_ajaran_id') ? TahunAjaran::find($request->tahun_ajaran_id) : $tahunAktif;
            if ($tahun) {
                $filters['tahun_ajaran'] = $tahun->nama;
                $filters['semester'] = strtoupper($tahun->semester); 
                $nameParts[] = str_replace(['/', ' '], '_', $tahun->nama) . '_' . strtoupper($tahun->semester);
            }

            if ($request->filled('kelas_id')) {
                $kelas = Kelas::find($request->kelas_id);
                if ($kelas) {
                    $kriteria[] = "KELAS " . strtoupper($kelas->nama_kelas);
                    $filters['kelas'] = $kelas->nama_kelas;
                    $nameParts[] = strtoupper(Str::slug($kelas->nama_kelas, '_'));
                }
            } else {
                $nameParts[] = strtoupper(Str::slug($namaJurusan, '_'));
            }

            if ($request->filled('guru_staf_id')) {
                $guru = GuruStaf::find($request->guru_staf_id);
                if ($guru) {
                    $kriteria[] = "GURU " . strtoupper($guru->nama);
                    $filters['guru'] = $guru->nama;
                    $nameParts[] = strtoupper(Str::slug($guru->nama, '_'));
                }
            }

            if ($request->filled('mata_pelajaran_id')) {
                $mapel = MataPelajaran::find($request->mata_pelajaran_id);
                if ($mapel) {
                    $kriteria[] = "MAPEL " . strtoupper($mapel->nama_mapel);
                    $filters['mapel'] = $mapel->nama_mapel;
                    $nameParts[] = strtoupper(Str::slug($mapel->nama_mapel, '_'));
                }
            }

            if ($request->filled('hari')) {
                $kriteria[] = "HARI " . strtoupper($request->hari);
            }

            $filters['identitas_laporan'] = implode(' | ', $kriteria);

            $nameParts[] = 'AKTIF';
            $fileName = implode('_', $nameParts) . '.xlsx';
            
            if (ob_get_contents()) ob_end_clean();

            return Excel::download(
                new GuruMapelExport(
                    $query, 
                    ProfilSekolah::first() ?? new ProfilSekolah(), 
                    DataKontak::first() ?? new DataKontak(), 
                    $filters
                ), 
                $fileName
            );
        } catch (Throwable $e) {
            Log::error('Export Ketua Jurusan Error: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal export.'], 500);
        }
    }
}