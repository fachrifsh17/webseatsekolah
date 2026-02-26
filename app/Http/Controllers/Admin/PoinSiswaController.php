<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{PoinSiswa, TahunAjaran, Siswa, Kelas};
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
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);
        $this->authorizeResource(PoinSiswa::class, 'poin_siswa');
    }

    private function getPoinWithKumulatif($id)
    {
        return PoinSiswa::with([
                'siswa.riwayatKelas' => fn($q) => $q->with('kelas'),
                'guruStaf', 
                'tahunAjaran'
            ])
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
            $taActive = TahunAjaran::where('is_active', true)->first();
            
            $query = PoinSiswa::with([
                    'siswa.riwayatKelas' => fn($q) => $q->with('kelas'),
                    'guruStaf', 
                    'tahunAjaran'
                ])
                ->select('poin_siswa.*')
                ->addSelect([
                    'total_kumulatif_positif' => DB::table('poin_siswa as ps')
                        ->whereColumn('ps.siswa_id', 'poin_siswa.siswa_id')
                        ->selectRaw('SUM(poin_positif)'),
                    'total_kumulatif_negatif' => DB::table('poin_siswa as ps')
                        ->whereColumn('ps.siswa_id', 'poin_siswa.siswa_id')
                        ->selectRaw('SUM(poin_negatif)')
                ]);

            if ($request->filled('kelas_id')) {
                $query->where('kelas_id', $request->kelas_id);
            }

            if ($request->filled('tahun_ajaran_id')) {
                $query->where('tahun_ajaran_id', $request->tahun_ajaran_id);
            } else {
                if ($taActive) {
                    $query->where('tahun_ajaran_id', $taActive->id);
                }
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
                $query->whereHas('siswa', function($q) use ($search) {
                    $q->where('nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('nisn', 'like', "%{$search}%")
                      ->orWhere('nis', 'like', "%{$search}%");
                });
            }

            $perPage = min((int) $request->get('per_page', 20), 100);
            $data = $query->orderByDesc('total_kumulatif_negatif')
                          ->orderByDesc('tanggal')
                          ->paginate($perPage);

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
                ->whereHas('riwayatKelas', function($q) use ($ta) {
                    $q->where('tahun_ajaran_id', $ta->id)
                      ->where('is_active', true);
                })
                ->first();

            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak ditemukan atau tidak terdaftar di kelas aktif tahun ajaran ini.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $poin = DB::transaction(function () use ($request, $ta, $user, $siswa) {
                $guruStafId = $request->guru_staf_id ?? ($user->guruStaf ? $user->guruStaf->id : null);
                $riwayatAktif = $siswa->riwayatKelas()->where('is_active', true)->first();
                $kelasId = $riwayatAktif ? $riwayatAktif->kelas_id : null;

                return PoinSiswa::create(array_merge($request->validated(), [
                    'tanggal' => now()->toDateString(),
                    'tahun_ajaran_id' => $ta->id,
                    'guru_staf_id' => $guruStafId,
                    'kelas_id' => $kelasId
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
            DB::transaction(fn() => $poinSiswa->update(array_merge($request->validated(), [
                'tanggal' => now()->toDateString()
            ])));
            
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

            $namaKelasLaporan = 'SELURUH SISWA';
            if ($request->filled('kelas_id')) {
                $kelasObj = Kelas::find($request->kelas_id);
                $namaKelasLaporan = $kelasObj ? $kelasObj->nama_kelas : $request->kelas_id;
            }

            $taSlug = strtoupper(str_replace([' ', '/', '\\'], '_', $namaTA));
            $kelasSlug = strtoupper(str_replace([' ', '/', '\\'], '_', $namaKelasLaporan));
            $fileName = "REKAP_POIN_SISWA_{$kelasSlug}_{$taSlug}.XLSX";

            $labelWaktu = $request->filled('bulan') ? date('F Y', strtotime($request->bulan)) : "KUMULATIF";

            $query = PoinSiswa::with([
                'siswa.riwayatKelas' => fn($q) => $q->with('kelas'),
                'guruStaf', 
                'tahunAjaran'
            ]);

            if ($request->filled('kelas_id')) {
                $query->where('kelas_id', $request->kelas_id);
            }

            if ($request->filled('tahun_ajaran_id')) {
                $query->where('tahun_ajaran_id', $request->tahun_ajaran_id);
            } elseif ($taActive) {
                $query->where('tahun_ajaran_id', $taActive->id);
            }

            if ($request->filled('bulan')) {
                $time = strtotime($request->bulan);
                $query->whereMonth('tanggal', date('m', $time))
                      ->whereYear('tanggal', date('Y', $time));
            }

            return Excel::download(
                new PoinSiswaExport(
                    $query->orderBy('tanggal', 'asc'), 
                    $namaKelasLaporan, 
                    $labelWaktu, 
                    $profil, 
                    $kontak, 
                    $namaTA 
                ),
                $fileName
            );
        } catch (Throwable $e) {
            Log::error('Export Poin Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengunduh laporan.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}