<?php

namespace App\Http\Controllers\KepalaSekolah;

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
            ->findOrFail($id);
    }

    public function index(Request $request): JsonResponse
    {
        try {
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

            // Filter Tahun Ajaran
            if ($request->filled('tahun_ajaran_id')) {
                $query->where('tahun_ajaran_id', $request->tahun_ajaran_id);
            } else {
                $query->whereHas('tahunAjaran', fn($q) => $q->where('is_active', true));
            }

            // Filter Siswa
            if ($request->filled('siswa_id')) {
                $query->where('siswa_id', $request->siswa_id);
            }
            
            // Filter Bulan
            if ($request->filled('bulan')) {
                $time = strtotime($request->bulan);
                $query->whereMonth('tanggal', date('m', $time))
                      ->whereYear('tanggal', date('Y', $time));
            }

            // Search Nama/NISN
            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('siswa', fn($q) => $q->where('nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('nisn', 'like', "%{$search}%"));
            }

            $perPage = min((int) $request->get('per_page', 20), 100);
            $data = $query->orderByDesc('total_kumulatif_negatif')
                          ->orderByDesc('tanggal')
                          ->paginate($perPage);

            // Metadata pagination lengkap
            $paginationData = $data->toArray();

            return response()->json([
                'success' => true,
                'data' => PoinSiswaResource::collection($data),
                'meta' => [
                    'current_page'  => $data->currentPage(),
                    'last_page'     => $data->lastPage(),
                    'per_page'      => $data->perPage(),
                    'total'         => $data->total(),
                    'from'          => $data->firstItem(),
                    'to'            => $data->lastItem(),
                    'next_page_url' => $data->nextPageUrl(),
                    'prev_page_url' => $data->previousPageUrl(),
                    'path'          => $paginationData['path'],
                    'links'         => $paginationData['links'],
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Fetch Poin Index Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal mengambil data poin.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StorePoinSiswaRequest $request): JsonResponse
    {
        try {
            $ta = TahunAjaran::where('is_active', true)->firstOrFail();
            $user = Auth::user()->load('guruStaf');

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

            $poin = DB::transaction(function () use ($request, $ta, $user) {
                $guruStafId = $request->guru_staf_id ?? ($user->guruStaf ? $user->guruStaf->id : null);

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
        return response()->json([
            'success' => true,
            'data' => new PoinSiswaResource($this->getPoinWithKumulatif($poinSiswa->id))
        ], Response::HTTP_OK);
    }

    public function update(UpdatePoinSiswaRequest $request, PoinSiswa $poinSiswa): JsonResponse
    {
        try {
            $siswa = Siswa::where('id', $poinSiswa->siswa_id)
                ->where('is_active', true)
                ->whereHas('kelas', fn($q) => $q->where('is_active', true))
                ->first();

            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak dapat diubah karena siswa atau kelas sudah tidak aktif.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            DB::transaction(fn() => $poinSiswa->update($request->validated()));
            
            return response()->json([
                'success' => true,
                'message' => 'Data poin berhasil diperbarui.',
                'data' => new PoinSiswaResource($this->getPoinWithKumulatif($poinSiswa->id))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Update Poin Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal memperbarui data poin.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(PoinSiswa $poinSiswa): JsonResponse
    {
        try {
            DB::transaction(fn() => $poinSiswa->delete());
            return response()->json([
                'success' => true, 
                'message' => 'Data poin berhasil dihapus.'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Delete Poin Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal menghapus data poin.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        try {
            $this->authorize('viewAny', PoinSiswa::class);

            $profil = DB::table('profil_sekolah')->first();
            $kontak = DB::table('data_kontak')->first();

            $taActive = $request->filled('tahun_ajaran_id') 
                ? TahunAjaran::find($request->tahun_ajaran_id) 
                : TahunAjaran::where('is_active', true)->first();
            
            $namaTA = $taActive ? $taActive->nama . " " . $taActive->semester : "-";
            
            $namaKelas = $request->nama_kelas ?? 'Seluruh_Siswa';
            $namaKelasFile = str_replace([' ', '/', '\\'], '_', $namaKelas);
            
            $labelWaktu = $request->filled('bulan') ? date('F Y', strtotime($request->bulan)) : "Kumulatif";
            $bulanFile = $request->filled('bulan') ? date('M_Y', strtotime($request->bulan)) : "Semua_Waktu";

            $query = PoinSiswa::with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])
                ->whereHas('siswa', function($q) {
                    $q->where('is_active', true)
                      ->whereHas('kelas', fn($qk) => $qk->where('is_active', true));
                });

            if ($request->filled('kelas_id')) {
                $query->whereHas('siswa', fn($q) => $q->where('kelas_id', $request->kelas_id));
            }

            if ($request->filled('tahun_ajaran_id')) {
                $query->where('tahun_ajaran_id', $request->tahun_ajaran_id);
            } else {
                $query->whereHas('tahunAjaran', fn($q) => $q->where('is_active', true));
            }

            $fileName = "Rekap_Poin_{$namaKelasFile}_{$bulanFile}_" . date('His') . ".xlsx";

            return Excel::download(
                new PoinSiswaExport(
                    $query->orderBy('tanggal', 'asc'), 
                    $namaKelas, 
                    $labelWaktu, 
                    $profil, 
                    $kontak, 
                    $namaTA 
                ),
                $fileName
            );
        } catch (Throwable $e) {
            Log::error('Export Poin Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengunduh laporan.'], 500);
        }
    }
}