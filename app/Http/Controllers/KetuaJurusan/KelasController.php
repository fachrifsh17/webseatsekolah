<?php

namespace App\Http\Controllers\KetuaJurusan;

use App\Http\Controllers\Controller;
use App\Models\{Kelas, Siswa, Jurusan, TahunAjaran, Semester, ProfilSekolah, DataKontak};
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
        
        $semesterAktif = Semester::with('tahunAjaran')->where('is_active', 1)->first();

        return [
            'jurusan_id' => $jurusanId,
            'semester_aktif' => $semesterAktif,
            'nama_jurusan' => $user->guruStaf?->jurusan?->nama_jurusan
        ];
    }

    public function index(Request $request): JsonResponse
    {
        ['jurusan_id' => $jurusanId, 'semester_aktif' => $sem] = $this->getContext();

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
                ->orderBy('tingkatan_id', 'asc')
                ->orderBy('nama_kelas', 'asc');

            if ($request->filled('search')) {
                $query->where('nama_kelas', 'like', '%' . $request->search . '%');
            }

            if ($request->filled('tingkatan_id')) {
                $query->where('tingkatan_id', $request->tingkatan_id);
            }

            $perPage = (int) $request->get('per_page', 10);
            $kelas = $query->paginate($perPage);
            
            $resource = KelasResource::collection($kelas)->response()->getData(true);

            return response()->json(array_merge([
                'success' => true,
                'message' => "Daftar kelas aktif jurusan berhasil dimuat.",
                'context' => [
                    'tahun_ajaran' => $sem?->tahunAjaran?->nama ?? '-',
                    'semester' => $sem?->nama ?? '-',
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
            $filters = $request->only(['search', 'tingkatan_id']);
            $filters['jurusan_id'] = $jurusanId;
            $filters['is_active'] = 1;
            
            $filters['identitas_laporan'] = "JURUSAN " . ($namaJurusan ?? 'Unit');

            $profil = ProfilSekolah::first() ?? new ProfilSekolah(); 
            $kontak = DataKontak::first() ?? new DataKontak(); 

            $sem = $request->filled('semester_id') 
                ? Semester::with('tahunAjaran')->find($request->semester_id) 
                : Semester::with('tahunAjaran')->where('is_active', true)->first();

            if ($sem) {
                $filters['semester_id'] = $sem->id;
                $taName = str_replace(['/', ' '], '_', $sem->tahunAjaran->nama);
                $semName = strtoupper($sem->nama);
                $jurusanSlug = strtoupper(Str::slug($namaJurusan, '_'));
                
                // Menambahkan "AKTIF" di bagian akhir nama file sesuai permintaan
                $fileName = "DATA_KELAS_{$jurusanSlug}_{$taName}_{$semName}_AKTIF.xlsx";
            } else {
                $fileName = "DATA_KELAS_AKTIF.xlsx";
            }

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