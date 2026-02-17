<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\{PoinSiswa, TahunAjaran, Siswa};
use App\Http\Requests\{StorePoinSiswaRequest, UpdatePoinSiswaRequest};
use App\Http\Resources\PoinSiswaResource;
use App\Exports\PoinSiswaExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Auth, Log};
use Maatwebsite\Excel\Facades\Excel;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class PoinSiswaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);
        $this->authorizeResource(PoinSiswa::class, 'poin_siswa');
    }

    private function getPoinWithKumulatif($id)
    {
        $user = Auth::user();
        $guruStafId = $user->guruStaf?->id;
        
        return PoinSiswa::with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])
            ->select('poin_siswa.*')
            ->addSelect([
                'total_kumulatif_positif' => DB::table('poin_siswa as ps')
                    ->whereColumn('ps.siswa_id', 'poin_siswa.siswa_id')
                    ->selectRaw('SUM(poin_positif)'),
                'total_kumulatif_negatif' => DB::table('poin_siswa as ps')
                    ->whereColumn('ps.siswa_id', 'poin_siswa.siswa_id')
                    ->selectRaw('SUM(poin_negatif)')
            ])
            ->where('poin_siswa.guru_staf_id', $guruStafId)
            ->findOrFail($id);
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $guruStafId = $user->guruStaf?->id;

            $query = PoinSiswa::with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])
                ->select('poin_siswa.*')
                ->addSelect([
                    'total_kumulatif_positif' => DB::table('poin_siswa as ps')
                        ->whereColumn('ps.siswa_id', 'poin_siswa.siswa_id')
                        ->selectRaw('SUM(poin_positif)'),
                    'total_kumulatif_negatif' => DB::table('poin_siswa as ps')
                        ->whereColumn('ps.siswa_id', 'poin_siswa.siswa_id')
                        ->selectRaw('SUM(poin_negatif)')
                ])
                ->whereHas('siswa', function($q) {
                    $q->where('is_active', true)
                      ->whereHas('kelas', fn($qk) => $qk->where('is_active', true));
                });

            $query->where('poin_siswa.guru_staf_id', $guruStafId);

            if ($request->filled('tahun_ajaran_id')) {
                $query->where('tahun_ajaran_id', $request->tahun_ajaran_id);
            } else {
                $query->whereHas('tahunAjaran', fn($q) => $q->where('is_active', true));
            }

            if ($request->filled('siswa_id')) {
                $query->where('siswa_id', $request->siswa_id);
            }
            
            if ($request->filled('bulan')) {
                $time = strtotime($request->bulan);
                $query->whereMonth('tanggal', date('m', $time))
                      ->whereYear('tanggal', date('Y', $time));
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('siswa', fn($q) => $q->where('nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('nisn', 'like', "%{$search}%"));
            }

            $perPage = min((int) $request->get('per_page', 20), 100);
            $data = $query->orderByDesc('tanggal')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => PoinSiswaResource::collection($data),
                'meta' => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'total'        => $data->total(),
                ],
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Fetch Poin Index Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal mengambil data.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StorePoinSiswaRequest $request): JsonResponse
    {
        try {
            $ta = TahunAjaran::where('is_active', true)->firstOrFail();
            $user = Auth::user();
            $guruStafId = $user->guruStaf?->id;

            if (!$guruStafId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun Anda tidak terhubung dengan data Guru/Staf.'
                ], Response::HTTP_FORBIDDEN);
            }

            $siswa = Siswa::where('id', $request->siswa_id)
                ->where('is_active', true)
                ->whereHas('kelas', fn($q) => $q->where('is_active', true))
                ->first();

            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak ditemukan atau kelas sudah tidak aktif.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $poin = DB::transaction(function () use ($request, $ta, $guruStafId) {
                return PoinSiswa::create(array_merge($request->validated(), [
                    'tahun_ajaran_id' => $ta->id,
                    'guru_staf_id' => $guruStafId 
                ]));
            });

            return response()->json([
                'success' => true,
                'message' => 'Poin berhasil dicatat.',
                'data' => new PoinSiswaResource($this->getPoinWithKumulatif($poin->id))
            ], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            Log::error('Store Poin Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal mencatat poin.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(PoinSiswa $poinSiswa): JsonResponse
    {
        try {
            $data = $this->getPoinWithKumulatif($poinSiswa->id);
            return response()->json([
                'success' => true, 
                'data' => new PoinSiswaResource($data)
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Data tidak ditemukan atau akses ditolak.'
            ], Response::HTTP_FORBIDDEN);
        }
    }

    public function update(UpdatePoinSiswaRequest $request, PoinSiswa $poinSiswa): JsonResponse
    {
        try {
            $poin = $this->getPoinWithKumulatif($poinSiswa->id);
            DB::transaction(fn() => $poin->update($request->validated()));
            
            return response()->json([
                'success' => true, 
                'message' => 'Berhasil diperbarui.', 
                'data' => new PoinSiswaResource($this->getPoinWithKumulatif($poin->id))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Gagal memperbarui data.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(PoinSiswa $poinSiswa): JsonResponse
    {
        try {
            $poin = $this->getPoinWithKumulatif($poinSiswa->id);
            DB::transaction(fn() => $poin->delete());
            
            return response()->json([
                'success' => true, 
                'message' => 'Berhasil dihapus.'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Gagal menghapus data.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        try {
            $user = Auth::user();
            $guruStafId = $user->guruStaf?->id;
            $profil = DB::table('profil_sekolah')->first();
            $kontak = DB::table('data_kontak')->first();
            $taActive = $request->filled('tahun_ajaran_id') 
                ? TahunAjaran::find($request->tahun_ajaran_id) 
                : TahunAjaran::where('is_active', true)->first();
            
            $query = PoinSiswa::with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])
                ->where('guru_staf_id', $guruStafId);

            return Excel::download(
                new PoinSiswaExport($query->orderBy('tanggal', 'asc'), 'Laporan', 'Periode', $profil, $kontak, $taActive?->nama),
                "Rekap_Poin_" . date('His') . ".xlsx"
            );
        } catch (Throwable $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Gagal melakukan export laporan.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}