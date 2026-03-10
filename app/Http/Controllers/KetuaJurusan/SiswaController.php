<?php

namespace App\Http\Controllers\KetuaJurusan;

use App\Http\Controllers\Controller;
use App\Models\{Siswa, Kelas, TahunAjaran, Semester};
use App\Http\Resources\SiswaResource;
use App\Exports\SiswaExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Log};
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SiswaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['export']);
    }

    private function getIdentity(): array
    {
        $user = Auth::user();
        $guru = $user->guruStaf; 

        if (!$guru) return [null, null, null];

        $semesterAktif = Semester::with('tahunAjaran')
            ->where('is_active', 1)
            ->whereHas('tahunAjaran', fn($q) => $q->where('is_active', 1))
            ->first();
            
        if (!$semesterAktif) return [$guru, null, null];

        $jurusan = $guru->jurusan; 

        return [$guru, $jurusan, $semesterAktif];
    }

    private function applyFilters(Request $request, $query, $jurusanId, $semesterId)
    {
        $isActive = $request->query('is_active', 1);

        $query->where('is_active', $isActive)
        ->whereHas('riwayatKelas', function ($q) use ($jurusanId, $semesterId) {
            $q->where('semester_id', $semesterId)
              ->where('is_active', 1)
              ->whereHas('kelas', function($qK) use ($jurusanId) {
                  $qK->where('jurusan_id', $jurusanId)
                     ->where('is_active', 1);
              });
        });

        if ($request->filled('tingkatan_id')) {
            $query->whereHas('riwayatKelas', function($q) use ($request, $semesterId) {
                $q->where('semester_id', $semesterId)
                  ->where('is_active', 1)
                  ->whereHas('kelas', function($qK) use ($request) {
                    $qK->where('tingkatan_id', $request->tingkatan_id)
                       ->where('is_active', 1);
                });
            });
        }

        if ($request->filled('kelas_id')) {
            $query->whereHas('riwayatKelas', function($q) use ($request, $semesterId) {
                $q->where('semester_id', $semesterId)
                  ->where('kelas_id', $request->kelas_id)
                  ->where('is_active', 1);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            [$guru, $jurusan, $semesterAktif] = $this->getIdentity();

            if (!$jurusan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data jurusan tidak ditemukan untuk akun Anda.'
                ], Response::HTTP_FORBIDDEN);
            }

            $semesterId = $request->query('semester_id', $semesterAktif?->id);

            $query = Siswa::where('is_active', 1)->with(['user', 'orangtua', 'riwayatKelas' => function($q) use ($semesterId) {
                $q->where('semester_id', $semesterId)
                  ->where('is_active', 1)
                  ->with(['kelas' => fn($qk) => $qk->where('is_active', 1)->with('tingkatan')]);
            }]);

            $query = $this->applyFilters($request, $query, $jurusan->id, $semesterId);

            $perPage = min((int) $request->query('per_page', 20), 100);
            $paginatedData = $query->orderByRaw('LOWER(nama_lengkap) ASC')->paginate($perPage);

            $transformedData = collect($paginatedData->items())->map(function($siswa) {
                $resource = (new SiswaResource($siswa))->toArray(request());
                
                if (isset($resource['kelas']['jurusan'])) {
                    unset($resource['kelas']['jurusan']);
                }

                if (isset($resource['orangtua'])) {
                    $resource['orangtua'] = collect($resource['orangtua'])->map(function($ortu) {
                        return [
                            'nama_lengkap' => $ortu['nama_lengkap'] ?? null
                        ];
                    })->values();
                }

                return $resource;
            });

            return response()->json([
                'success' => true,
                'message' => "Daftar siswa jurusan berhasil dimuat.",
                'info'    => [
                    'id_jurusan'   => $jurusan->id,
                    'nama_jurusan' => $jurusan->nama_jurusan,
                    'tahun_aktif'  => $semesterAktif->tahunAjaran?->nama . " (" . $semesterAktif->nama . ")"
                ],
                'data'    => $transformedData,
                'meta'    => [
                    'current_page'  => $paginatedData->currentPage(),
                    'last_page'     => $paginatedData->lastPage(),
                    'per_page'      => $paginatedData->perPage(),
                    'total'         => $paginatedData->total(),
                    'has_more'      => $paginatedData->hasMorePages(),
                    'next_page_url' => $paginatedData->nextPageUrl(),
                    'prev_page_url' => $paginatedData->previousPageUrl(),
                ]
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Kajur Siswa Index Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data siswa jurusan.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            [$guru, $jurusan, $semesterAktif] = $this->getIdentity();

            if (!$jurusan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data jurusan tidak ditemukan.'
                ], Response::HTTP_FORBIDDEN);
            }

            $siswa = Siswa::where('is_active', 1)
            ->with(['user', 'orangtua', 'riwayatKelas' => function($q) use ($semesterAktif) {
                $q->where('semester_id', $semesterAktif->id)
                  ->where('is_active', 1)
                  ->with(['kelas' => fn($qk) => $qk->where('is_active', 1)->with('tingkatan')]);
            }])
            ->whereHas('riwayatKelas', function ($q) use ($jurusan, $semesterAktif) {
                $q->where('semester_id', $semesterAktif->id)
                  ->where('is_active', 1)
                  ->whereHas('kelas', function($qK) use ($jurusan) {
                      $qK->where('jurusan_id', $jurusan->id)
                         ->where('is_active', 1);
                  });
            })
            ->findOrFail($id);

            $resource = (new SiswaResource($siswa))->toArray(request());

            if (isset($resource['kelas']['jurusan'])) {
                unset($resource['kelas']['jurusan']);
            }

            return response()->json([
                'success' => true,
                'message' => "Detail siswa berhasil dimuat.",
                'data'    => $resource
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Kajur Siswa Show Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Siswa tidak ditemukan atau Anda tidak memiliki akses.',
            ], Response::HTTP_NOT_FOUND);
        }
    }

    public function export(Request $request)
    {
        try {
            [$guru, $jurusan, $semesterAktif] = $this->getIdentity();

            if (!$jurusan) {
                return response()->json(['success' => false, 'message' => 'Jurusan tidak ditemukan.'], Response::HTTP_FORBIDDEN);
            }

            $semesterId = $request->query('semester_id', $semesterAktif?->id);

            $query = Siswa::where('is_active', 1)->with(['user', 'orangtua', 'riwayatKelas.kelas.jurusan', 'riwayatKelas.kelas.tingkatan']);
            $query = $this->applyFilters($request, $query, $jurusan->id, $semesterId);
            $query->orderByRaw('LOWER(nama_lengkap) ASC');

            $kelasObj = null;
            if ($request->filled('kelas_id')) {
                $kelasObj = Kelas::where('is_active', 1)->with(['tingkatan', 'jurusan'])->find($request->kelas_id);
            }

            $statusStr = $request->query('is_active', 1) ? 'AKTIF' : 'TIDAK_AKTIF';
            
            $filterInfo = [
                'jurusan' => $jurusan->nama_jurusan,
                'tingkat' => $kelasObj ? ($kelasObj->tingkatan->nama_tingkatan ?? '-') : ($request->filled('tingkatan_id') ? DB::table('tingkatan')->where('id', $request->tingkatan_id)->value('nama_tingkatan') : 'SEMUA TINGKATAN'),
                'kelas'   => $kelasObj ? $kelasObj->nama_kelas : 'SEMUA KELAS',
                'status'  => $statusStr
            ];

            $nameParts = ['DATA_SISWA'];
            $nameParts[] = strtoupper(str_replace([' ', '-'], '_', $kelasObj ? $kelasObj->nama_kelas : $jurusan->nama_jurusan));

            if ($semesterAktif) {
                $nameParts[] = strtoupper(str_replace(['/', ' '], '_', $semesterAktif->tahunAjaran->nama));
                $nameParts[] = strtoupper($semesterAktif->nama);
            }

            $nameParts[] = $statusStr;
            $fileName = implode('_', $nameParts) . '.xlsx';

            if (ob_get_contents()) ob_end_clean();

            return Excel::download(
                new SiswaExport(
                    $query, 
                    DB::table('profil_sekolah')->first(), 
                    DB::table('data_kontak')->first(), 
                    $kelasObj, 
                    $filterInfo
                ), 
                $fileName
            );

        } catch (Throwable $e) {
            Log::error('Kajur Siswa Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal ekspor data siswa jurusan.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}