<?php

namespace App\Http\Controllers\Walikelas;

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
        $this->middleware('role:Guru');
        $this->middleware('log.aktivitas')->only(['export']);
    }

    private function getIdentity(): array
    {
        $user = Auth::user();
        $guru = $user->guruStaf; 

        if (!$guru) return [null, null, null];

        $taAktif = TahunAjaran::where('is_active', true)->first();
        if (!$taAktif) return [$guru, null, null];

        $kelas = Kelas::where('wali_kelas_id', $guru->id)
            ->where('is_active', 1)
            ->first();

        return [$guru, $kelas, $taAktif];
    }

    private function applyFilters(Request $request, $query, $kelasId, $taId)
    {
        $query->whereHas('riwayatKelas', function ($q) use ($kelasId, $taId) {
            $q->where('kelas_id', $kelasId)
              ->where('tahun_ajaran_id', $taId)
              ->where('is_active', 1);
        });

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
            [$guru, $kelas, $taAktif] = $this->getIdentity();

            if (!$kelas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki kelas perwalian aktif di tahun ajaran ini.'
                ], Response::HTTP_FORBIDDEN);
            }

            $query = Siswa::with(['user', 'orangtua']);
            $query = $this->applyFilters($request, $query, $kelas->id, $taAktif->id);

            // PAGINATION & SORTING A-Z
            $perPage = min((int) $request->get('per_page', 20), 100);
            $paginatedData = $query->orderBy('nama_lengkap', 'asc')->paginate($perPage);

            $transformedData = collect($paginatedData->items())->map(function($siswa) {
                $resource = (new SiswaResource($siswa))->toArray(request());
                
                unset($resource['kelas']);

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
                'message' => "Daftar siswa berhasil dimuat.",
                'info'    => [
                    'id_kelas'    => $kelas->id,
                    'nama_kelas'  => $kelas->nama_kelas,
                    'tahun_aktif' => $taAktif->nama . " (" . $taAktif->semester . ")"
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
            Log::error('Walikelas Siswa Index Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data siswa.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            [$guru, $kelas, $taAktif] = $this->getIdentity();

            if (!$kelas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Anda bukan wali kelas aktif.'
                ], Response::HTTP_FORBIDDEN);
            }

            // Pastikan siswa yang dicari memang anggota kelas perwaliannya
            $siswa = Siswa::with(['user', 'orangtua', 'riwayatKelas' => function($q) use ($taAktif) {
                $q->where('tahun_ajaran_id', $taAktif->id)->with('kelas');
            }])
            ->whereHas('riwayatKelas', function ($q) use ($kelas, $taAktif) {
                $q->where('kelas_id', $kelas->id)
                  ->where('tahun_ajaran_id', $taAktif->id);
            })
            ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => "Detail siswa berhasil dimuat.",
                'data'    => new SiswaResource($siswa)
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Walikelas Siswa Show Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Siswa tidak ditemukan dalam kelas perwalian Anda.',
            ], Response::HTTP_NOT_FOUND);
        }
    }

    public function export(Request $request)
    {
        try {
            [$guru, $kelas, $taAktif] = $this->getIdentity();

            if (!$kelas) {
                return response()->json(['success' => false, 'message' => 'Kelas tidak ditemukan.'], Response::HTTP_FORBIDDEN);
            }

            $query = Siswa::query();
            $query = $this->applyFilters($request, $query, $kelas->id, $taAktif->id);
            
            // Urutkan A-Z juga saat ekspor
            $query->orderBy('nama_lengkap', 'asc');

            $nameParts = ['DATA_SISWA'];
            $nameParts[] = strtoupper(str_replace([' ', '-'], '_', $kelas->nama_kelas));
            
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
                    $kelas,
                    $request->all()
                ), 
                $fileName
            );

        } catch (Throwable $e) {
            Log::error('Walikelas Siswa Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal ekspor data.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}