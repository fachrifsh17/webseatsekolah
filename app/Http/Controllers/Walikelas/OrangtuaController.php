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
        $guruId = Auth::user()->guruStaf?->id;
        if (!$guruId) return null;

        return Kelas::with(['jurusan'])
            ->where('wali_kelas_id', $guruId)
            ->where('is_active', 1)
            ->first();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $kelas = $this->getKelasPerwalian();
            if (!$kelas) {
                return response()->json(['success' => false, 'message' => 'Anda tidak memiliki kelas perwalian aktif.'], Response::HTTP_FORBIDDEN);
            }

            $taAktif = DB::table('tahun_ajaran')->where('is_active', 1)->first();
            $taId = $taAktif?->id;

            $query = Orangtua::with(['user', 'anak' => function($q) use ($kelas, $taId) {
                $q->whereHas('riwayatKelas', function($rq) use ($kelas, $taId) {
                    $rq->where('kelas_id', $kelas->id)
                      ->where('is_active', 1);
                    if ($taId) $rq->where('tahun_ajaran_id', $taId);
                })->with(['riwayatKelas' => function($rq) use ($kelas, $taId) {
                    $rq->where('kelas_id', $kelas->id)
                      ->where('is_active', 1);
                    if ($taId) $rq->where('tahun_ajaran_id', $taId);
                    $rq->with('kelas.jurusan');
                }]);
            }])->whereHas('anak.riwayatKelas', function ($q) use ($kelas, $taId) {
                $q->where('kelas_id', $kelas->id)
                  ->where('is_active', 1);
                if ($taId) $q->where('tahun_ajaran_id', $taId);
            });

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('telepon', 'like', "%{$search}%")
                      ->orWhereHas('anak', function ($qa) use ($search) {
                          $qa->where('nama_lengkap', 'like', "%{$search}%")
                             ->orWhere('nis', 'like', "%{$search}%");
                      });
                });
            }

            $perPage = min((int) $request->get('per_page', 20), 100);
            $paginatedData = $query->latest()->paginate($perPage);

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
                    'jurusan'    => $kelas->jurusan?->nama_jurusan,
                    'tahun_aktif'=> $taAktif ? $taAktif->nama . " (" . $taAktif->semester . ")" : "-"
                ],
                'data'    => $transformedData,
                'meta'    => [
                    'current_page' => $paginatedData->currentPage(),
                    'last_page'    => $paginatedData->lastPage(),
                    'total'        => $paginatedData->total(),
                ]
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Walikelas Orangtua Index Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal mengambil data: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        try {
            $kelas = $this->getKelasPerwalian();
            if (!$kelas) return response()->json(['success' => false, 'message' => 'Kelas tidak ditemukan.'], Response::HTTP_FORBIDDEN);

            $taAktif = DB::table('tahun_ajaran')->where('is_active', 1)->first();
            $taId = $taAktif?->id;

            $query = Orangtua::query()
                ->whereHas('anak.riwayatKelas', function ($q) use ($kelas, $taId) {
                    $q->where('kelas_id', $kelas->id)
                      ->where('is_active', 1);
                    if ($taId) $q->where('tahun_ajaran_id', $taId);
                });

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('telepon', 'like', "%{$search}%")
                      ->orWhereHas('anak', function ($qa) use ($search) {
                          $qa->where('nama_lengkap', 'like', "%{$search}%")
                             ->orWhere('nis', 'like', "%{$search}%");
                      });
                });
            }

            $nameParts = ['DATA_ORANGTUA', strtoupper(str_replace(' ', '_', $kelas->nama_kelas))];
            if ($taAktif) {
                $nameParts[] = strtoupper(str_replace(['/', ' '], '_', $taAktif->nama));
            }
            
            $filename = implode('_', $nameParts) . '.xlsx';
            if (ob_get_contents()) ob_end_clean();

            return Excel::download(
                new OrangtuaExport($query, DB::table('profil_sekolah')->first(), DB::table('data_kontak')->first(), $kelas, $request->all(), $kelas->jurusan), 
                $filename
            );

        } catch (Throwable $e) {
            Log::error('Export Orangtua Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengekspor data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}