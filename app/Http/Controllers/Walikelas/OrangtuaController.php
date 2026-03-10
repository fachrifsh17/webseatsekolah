<?php

namespace App\Http\Controllers\Walikelas;

use App\Http\Controllers\Controller;
use App\Models\{Orangtua, Kelas, User, Siswa, TahunAjaran};
use App\Http\Resources\OrangtuaResource;
use App\Exports\OrangtuaExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Log, Hash};
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class OrangtuaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Guru');
        $this->middleware('log.aktivitas')->only(['export']);
    }

    private function getKelasPerwalian()
    {
        $guru = Auth::user()->guruStaf;
        if (!$guru) return null;

        return Kelas::with(['jurusan', 'tingkatan'])
            ->whereHas('kelasWali', function ($q) use ($guru) {
                $q->where('guru_staf_id', $guru->id)
                  ->where('is_active', 1);
            })
            ->where('is_active', 1)
            ->first();
    }

    private function applyFilters(Request $request, $query, $kelas, $semesterId)
    {
        $isActive = $request->query('is_active', 1);

        $query->where('is_active', $isActive)
            ->whereHas('anak.riwayatKelas', function ($q) use ($kelas, $semesterId) {
                $q->where('kelas_id', $kelas->id)
                  ->where('is_active', 1);
                
                if ($semesterId) {
                    $q->where('semester_id', $semesterId);
                }
                
                $q->whereHas('kelas', function($qK) {
                    $qK->where('is_active', 1);
                });
            });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('telepon', 'like', "%{$search}%")
                  ->orWhereHas('anak', function ($qa) use ($search) {
                      $qa->where('nama_lengkap', 'like', "%{$search}%")
                         ->orWhere('nis', 'like', "%{$search}%")
                         ->orWhere('nisn', 'like', "%{$search}%");
                  });
            });
        }

        return $query;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $kelas = $this->getKelasPerwalian();
            if (!$kelas) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Anda tidak memiliki kelas perwalian aktif.'
                ], Response::HTTP_FORBIDDEN);
            }

            $semesterAktif = DB::table('semesters')->where('is_active', 1)->first();
            $semesterId = $semesterAktif?->id;

            $query = Orangtua::with(['user', 'anak' => function($q) use ($kelas, $semesterId) {
                $q->whereHas('riwayatKelas', function($rq) use ($kelas, $semesterId) {
                    $rq->where('kelas_id', $kelas->id)->where('is_active', 1);
                    if ($semesterId) $rq->where('semester_id', $semesterId);
                })->with(['riwayatKelas' => function($rq) use ($kelas, $semesterId) {
                    $rq->where('kelas_id', $kelas->id)->where('is_active', 1);
                    if ($semesterId) $rq->where('semester_id', $semesterId);
                    $rq->with(['kelas.jurusan', 'kelas.tingkatan']);
                }]);
            }]);

            $query = $this->applyFilters($request, $query, $kelas, $semesterId);

            $perPage = min((int) $request->query('per_page', 20), 100);
            
            $paginatedData = $query->orderBy('nama_lengkap', 'ASC')->paginate($perPage);

            $transformedData = collect($paginatedData->items())->map(function($ortu) {
                $data = (new OrangtuaResource($ortu))->toArray(request());
                if (isset($data['user'])) unset($data['user']);
                if (isset($data['anak'])) {
                    $data['anak'] = collect($data['anak'])->map(function($anak) {
                        unset($anak['kelas']);
                        return $anak;
                    });
                }
                return $data;
            });

            return response()->json([
                'success' => true,
                'message' => "Daftar orang tua siswa kelas {$kelas->nama_kelas} berhasil dimuat.",
                'info'   => [
                    'id_kelas'   => $kelas->id,
                    'nama_kelas' => $kelas->nama_kelas,
                    'tingkatan'  => $kelas->tingkatan?->nama_tingkatan,
                    'jurusan'    => $kelas->jurusan?->nama_jurusan,
                    'semester'   => $semesterAktif?->nama ?? "-"
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
            Log::error('Walikelas Orangtua Index Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal mengambil data orang tua.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        try {
            $kelas = $this->getKelasPerwalian();
            if (!$kelas) {
                return response()->json(['success' => false, 'message' => 'Kelas tidak ditemukan.'], Response::HTTP_FORBIDDEN);
            }

            $semesterAktif = DB::table('semesters')
                ->join('tahun_ajaran', 'semesters.tahun_ajaran_id', '=', 'tahun_ajaran.id')
                ->where('semesters.is_active', 1)
                ->select('semesters.*', 'tahun_ajaran.nama as nama_ta')
                ->first();
            
            $semesterId = $semesterAktif?->id;

            $query = Orangtua::with(['anak.riwayatKelas.kelas.tingkatan']);
            $query = $this->applyFilters($request, $query, $kelas, $semesterId);
            
            $query->orderBy('nama_lengkap', 'ASC');

            $namaKelasFile = strtoupper(str_replace(' ', '_', $kelas->nama_kelas));
            $nameParts = ['DATA_ORANGTUA', $namaKelasFile];
            
            if ($semesterAktif) {
                $taWithUnderscore = str_replace('/', '_', $semesterAktif->nama_ta);
                $taWithUnderscore = str_replace('-', '_', $taWithUnderscore);
                $nameParts[] = $taWithUnderscore;
                $nameParts[] = strtoupper(str_replace(' ', '_', $semesterAktif->nama));
            }
            
            $isActive = $request->query('is_active', 1);
            $nameParts[] = $isActive ? 'AKTIF' : 'TIDAK_AKTIF';

            $filename = implode('_', $nameParts) . '.xlsx';

            $filters = $request->all();
            $filters['semester_id'] = $semesterId;

            if (ob_get_contents()) ob_end_clean();

            return Excel::download(
                new OrangtuaExport(
                    $query, 
                    DB::table('profil_sekolah')->first(), 
                    DB::table('data_kontak')->first(), 
                    $kelas, 
                    $filters, 
                    $kelas->jurusan
                ), 
                $filename
            );

        } catch (Throwable $e) {
            Log::error('Export Orangtua Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal mengekspor data orang tua.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}