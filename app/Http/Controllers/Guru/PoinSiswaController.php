<?php

namespace App\Http\Controllers\Guru;

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
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);
        $this->authorizeResource(PoinSiswa::class, 'poin_siswa');
    }

    private function getPoinWithKumulatif($id)
    {
        $user = Auth::user();
        $guruStafId = $user->guruStaf?->id;
        
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
            ->where('poin_siswa.guru_staf_id', $guruStafId)
            ->findOrFail($id);
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $guruStafId = $user->guruStaf?->id;
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
                ])
                ->where('poin_siswa.guru_staf_id', $guruStafId);

            $query->whereHas('siswa.riwayatKelas', function($q) use ($request, $taActive) {
                if ($request->filled('kelas_id')) {
                    $q->where('kelas_id', $request->kelas_id);
                }

                if ($request->filled('tahun_ajaran_id')) {
                    $q->where('tahun_ajaran_id', $request->tahun_ajaran_id);
                } else if ($taActive) {
                    $q->where('tahun_ajaran_id', $taActive->id);
                }
            });

            if ($request->filled('tahun_ajaran_id')) {
                $query->where('poin_siswa.tahun_ajaran_id', $request->tahun_ajaran_id);
            } else if ($taActive) {
                $query->where('poin_siswa.tahun_ajaran_id', $taActive->id);
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
                      ->orWhere('nisn', 'like', "%{$search}%")
                      ->orWhere('nis', 'like', "%{$search}%"));
            }

            $perPage = min((int) $request->get('per_page', 20), 100);
            $data = $query->orderByDesc('tanggal')->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => PoinSiswaResource::collection($data),
                'meta' => [
                    'current_page'  => $data->currentPage(),
                    'last_page'     => $data->lastPage(),
                    'per_page'      => $data->perPage(),
                    'total'         => $data->total(),
                ],
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Fetch Poin Guru Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengambil data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StorePoinSiswaRequest $request): JsonResponse
    {
        try {
            $ta = TahunAjaran::where('is_active', true)->firstOrFail();
            $user = Auth::user();
            $guruStafId = $user->guruStaf?->id;

            if (!$guruStafId) {
                return response()->json(['success' => false, 'message' => 'Akun Anda tidak terhubung dengan data Guru/Staf.'], Response::HTTP_FORBIDDEN);
            }

            $siswa = Siswa::where('id', $request->siswa_id)
                ->where('is_active', true)
                ->whereHas('riwayatKelas', function($q) use ($ta) {
                    $q->where('is_active', true)->where('tahun_ajaran_id', $ta->id);
                })
                ->with(['riwayatKelas' => fn($q) => $q->where('is_active', true)->where('tahun_ajaran_id', $ta->id)])
                ->first();

            $riwayatAktif = $siswa?->riwayatKelas->first();

            if (!$siswa || !$riwayatAktif) {
                return response()->json(['success' => false, 'message' => 'Siswa tidak ditemukan atau tidak memiliki kelas aktif pada periode ini.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $poin = DB::transaction(function () use ($request, $ta, $guruStafId, $riwayatAktif) {
                return PoinSiswa::create(array_merge($request->validated(), [
                    'tahun_ajaran_id' => $ta->id,
                    'guru_staf_id'    => $guruStafId,
                    'kelas_id'        => $riwayatAktif->kelas_id,
                    'tanggal'         => now()->format('Y-m-d'),
                ]));
            });

            return response()->json([
                'success' => true,
                'message' => 'Poin berhasil dicatat.',
                'data' => new PoinSiswaResource($this->getPoinWithKumulatif($poin->id))
            ], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            Log::error('Store Poin Guru Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mencatat poin.'], Response::HTTP_INTERNAL_SERVER_ERROR);
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
            DB::transaction(fn() => $poinSiswa->update($request->validated()));
            
            return response()->json([
                'success' => true,
                'message' => 'Data poin berhasil diperbarui.',
                'data' => new PoinSiswaResource($this->getPoinWithKumulatif($poinSiswa->id))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Update Poin Guru Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(PoinSiswa $poinSiswa): JsonResponse
    {
        try {
            DB::transaction(fn() => $poinSiswa->delete());
            return response()->json(['success' => true, 'message' => 'Data poin berhasil dihapus.'], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Delete Poin Guru Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menghapus data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
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

            $query = PoinSiswa::with([
                    'siswa.riwayatKelas' => fn($q) => $q->with('kelas'), 
                    'guruStaf', 
                    'tahunAjaran'
                ])
                ->where('guru_staf_id', $guruStafId);

            $query->whereHas('siswa.riwayatKelas', function($q) use ($request, $taActive) {
                if ($request->filled('kelas_id')) {
                    $q->where('kelas_id', $request->kelas_id);
                }

                if ($request->filled('tahun_ajaran_id')) {
                    $q->where('tahun_ajaran_id', $request->tahun_ajaran_id);
                } else if ($taActive) {
                    $q->where('tahun_ajaran_id', $taActive->id);
                }
            });

            return Excel::download(
                new PoinSiswaExport(
                    $query->orderBy('tanggal', 'asc'), 
                    'REKAP GURU', 
                    'Periode', 
                    $profil, 
                    $kontak, 
                    $taActive?->nama
                ),
                "Rekap_Poin_Guru_" . date('His') . ".xlsx"
            );
        } catch (Throwable $e) {
            Log::error('Export Poin Guru Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal melakukan export laporan.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}