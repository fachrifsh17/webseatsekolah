<?php

namespace App\Http\Controllers\KetuaJurusan;

use App\Http\Controllers\Controller;
use App\Models\{Kelas, Siswa, Jurusan, TahunAjaran, ProfilSekolah, DataKontak};
use App\Http\Resources\KelasResource;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Log, Auth};
use Throwable;
use Symfony\Component\HttpFoundation\Response;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\KelasExport;
use Illuminate\Support\Str;

class KelasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['export']);
        
        $this->authorizeResource(Kelas::class, 'kelas');
    }

    private function getContext()
    {
        $user = Auth::user();
        $jurusanId = $user->guruStaf?->jurusan_id;
        $tahunAktif = TahunAjaran::where('is_active', 1)->first();

        return [
            'jurusan_id' => $jurusanId,
            'tahun_aktif' => $tahunAktif,
            'nama_jurusan' => $user->guruStaf?->jurusan?->nama_jurusan
        ];
    }

    public function index(Request $request): JsonResponse
    {
        ['jurusan_id' => $jurusanId, 'tahun_aktif' => $tahunAktif] = $this->getContext();

        if (!$jurusanId) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Profil jurusan tidak ditemukan.'
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $query = Kelas::with(['jurusan', 'waliKelas'])
                ->withCount(['siswa' => function ($q) {
                    $q->where('siswa_kelas.is_active', 1);
                }])
                ->where('jurusan_id', $jurusanId)
                ->where('is_active', 1)
                ->orderBy('nama_kelas', 'asc');

            if ($request->filled('search')) {
                $query->where('nama_kelas', 'like', '%' . $request->search . '%');
            }

            $perPage = (int) $request->get('per_page', 10);
            $kelas = $query->paginate($perPage);
            
            $resource = KelasResource::collection($kelas)->response()->getData(true);

            return response()->json(array_merge([
                'success' => true,
                'message' => "Daftar kelas aktif jurusan berhasil dimuat.",
                'context' => [
                    'tahun_ajaran' => $tahunAktif?->nama ?? '-',
                    'semester' => $tahunAktif?->semester ?? '-',
                    'jurusan' => $this->getContext()['nama_jurusan']
                ]
            ], $resource), Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Index Kelas Kajur Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar kelas.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', Kelas::class);
        ['jurusan_id' => $jurusanId, 'nama_jurusan' => $namaJurusan] = $this->getContext();

        if (!$jurusanId) {
            abort(Response::HTTP_FORBIDDEN, 'Data tidak lengkap untuk ekspor.');
        }

        try {
            $filters = $request->only(['search', 'tahun_ajaran_id']);
            $filters['jurusan_id'] = $jurusanId;
            $filters['is_active'] = 1;
            $filters['identitas_laporan'] = "JURUSAN " . strtoupper($namaJurusan ?? 'Unit');

            $profil = ProfilSekolah::first() ?? new ProfilSekolah(); 
            $kontak = DataKontak::first() ?? new DataKontak(); 

            $nameParts = ['DATA_KELAS'];

            if ($request->filled('search')) {
                $nameParts[] = strtoupper(Str::slug($request->search, '_'));
            }

            if ($namaJurusan) {
                $cleanJurusan = strtoupper(str_replace([' ', '-'], '_', preg_replace('/[^A-Za-z0-9 ]/', '', $namaJurusan)));
                $nameParts[] = $cleanJurusan;
            }

            $ta = $request->filled('tahun_ajaran_id') 
                ? TahunAjaran::find($request->tahun_ajaran_id) 
                : TahunAjaran::where('is_active', true)->first();

            if ($ta) {
                $filters['tahun_ajaran_id'] = $ta->id;
                $taName = str_replace(['/', ' '], '_', $ta->nama);
                $semester = strtoupper($ta->semester);
                $nameParts[] = "{$taName}_{$semester}";
            }

            $nameParts[] = 'AKTIF';
            $fileName = implode('_', $nameParts) . '.xlsx';

            if (ob_get_contents()) ob_end_clean();

            return Excel::download(new KelasExport($filters, $profil, $kontak), $fileName);
        } catch (Throwable $e) {
            Log::error('Export Kelas Kajur Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal mengekspor data kelas.',
                'errors' => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Kelas $kelas): JsonResponse
    {
        ['jurusan_id' => $jurusanId] = $this->getContext();

        if ($kelas->jurusan_id !== $jurusanId || $kelas->is_active != 1) {
            return response()->json(['success' => false, 'message' => 'Akses dilarang.'], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'success' => true,
            'data' => new KelasResource($kelas->load(['jurusan', 'waliKelas'])->loadCount(['siswa' => function($q){
                $q->where('siswa_kelas.is_active', 1);
            }])),
        ], Response::HTTP_OK);
    }
}