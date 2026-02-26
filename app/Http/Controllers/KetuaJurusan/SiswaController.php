<?php

namespace App\Http\Controllers\KetuaJurusan;

use App\Http\Controllers\Controller;
use App\Models\{Siswa, Kelas, TahunAjaran};
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

        $taAktif = TahunAjaran::where('is_active', true)->first();
        if (!$taAktif) return [$guru, null, null];

        $jurusan = $guru->jurusan; 

        return [$guru, $jurusan, $taAktif];
    }

    private function applyFilters(Request $request, $query, $jurusanId, $taId)
    {
        $query->whereHas('riwayatKelas', function ($q) use ($jurusanId, $taId) {
            $q->where('tahun_ajaran_id', $taId)
              ->where('is_active', 1)
              ->whereHas('kelas', function($qK) use ($jurusanId) {
                  $qK->where('jurusan_id', $jurusanId);
              });
        });

        if ($request->filled('kelas_id')) {
            $query->whereHas('riwayatKelas', function($q) use ($request) {
                $q->where('kelas_id', $request->kelas_id);
            });
        }

        $isActive = $request->get('is_active', 1);
        $query->where('is_active', $isActive);

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
            [$guru, $jurusan, $taAktif] = $this->getIdentity();

            if (!$jurusan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data jurusan tidak ditemukan untuk akun Anda.'
                ], Response::HTTP_FORBIDDEN);
            }

            $query = Siswa::with(['user', 'orangtua', 'riwayatKelas.kelas']);
            $query = $this->applyFilters($request, $query, $jurusan->id, $taAktif->id);

            $perPage = min((int) $request->get('per_page', 20), 100);
            
            // Penyesuaian: Menggunakan orderByRaw agar case-insensitive (A-Z)
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
                    'tahun_aktif'  => $taAktif->nama . " (" . $taAktif->semester . ")"
                ],
                'data'    => $transformedData,
                'meta'    => [
                    'current_page' => $paginatedData->currentPage(),
                    'last_page'    => $paginatedData->lastPage(),
                    'per_page'     => $paginatedData->perPage(),
                    'total'        => $paginatedData->total(),
                    'has_more'     => $paginatedData->hasMorePages(),
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
            [$guru, $jurusan, $taAktif] = $this->getIdentity();

            if (!$jurusan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data jurusan tidak ditemukan.'
                ], Response::HTTP_FORBIDDEN);
            }

            $siswa = Siswa::with(['user', 'orangtua', 'riwayatKelas' => function($q) use ($taAktif) {
                $q->where('tahun_ajaran_id', $taAktif->id)->with('kelas');
            }])
            ->whereHas('riwayatKelas', function ($q) use ($jurusan, $taAktif) {
                $q->where('tahun_ajaran_id', $taAktif->id)
                  ->whereHas('kelas', function($qK) use ($jurusan) {
                      $qK->where('jurusan_id', $jurusan->id);
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
            [$guru, $jurusan, $taAktif] = $this->getIdentity();

            if (!$jurusan) {
                return response()->json(['success' => false, 'message' => 'Jurusan tidak ditemukan.'], Response::HTTP_FORBIDDEN);
            }

            $query = Siswa::with(['user', 'orangtua', 'riwayatKelas.kelas.jurusan']);
            $query = $this->applyFilters($request, $query, $jurusan->id, $taAktif->id);
            
            // Penyesuaian: Menggunakan orderByRaw agar export juga case-insensitive
            $query->orderByRaw('LOWER(nama_lengkap) ASC');

            $nameParts = ['DATA_SISWA'];
            
            if ($request->filled('kelas_id')) {
                $kelasObj = Kelas::find($request->kelas_id);
                $identityExport = $kelasObj->nama_kelas ?? 'KELAS';
                $nameParts[] = strtoupper(str_replace([' ', '-'], '_', $identityExport));
            } else {
                $identityExport = "Jurusan " . $jurusan->nama_jurusan;
                $nameParts[] = strtoupper(str_replace([' ', '-'], '_', $jurusan->nama_jurusan));
            }
            
            if ($taAktif) {
                $nameParts[] = strtoupper(str_replace(['/', ' '], '_', $taAktif->nama));
                $nameParts[] = strtoupper($taAktif->semester);
            }
            
            $isActive = $request->get('is_active', 1);
            $nameParts[] = $isActive ? 'AKTIF' : 'TIDAK_AKTIF';
            
            $fileName = implode('_', $nameParts) . '.xlsx';

            if (ob_get_contents()) ob_end_clean();

            return Excel::download(
                new SiswaExport(
                    $query, 
                    DB::table('profil_sekolah')->first(), 
                    DB::table('data_kontak')->first(), 
                    $identityExport, 
                    $request->all()
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