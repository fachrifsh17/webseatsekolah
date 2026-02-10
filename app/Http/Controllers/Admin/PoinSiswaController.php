<?php

namespace App\Http\Controllers\Admin;

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
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
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
                $query->whereMonth('tanggal', date('m', $time))->whereYear('tanggal', date('Y', $time));
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('siswa', fn($q) => $q->where('nama_lengkap', 'like', "%{$search}%")->orWhere('nisn', 'like', "%{$search}%"));
            }

            $perPage = min((int) $request->get('per_page', 20), 100);
            $data = $query->orderByDesc('total_kumulatif_negatif')
                          ->orderByDesc('tanggal')
                          ->paginate($perPage);

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
            Log::error('Admin Poin Index Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengambil data.'], 500);
        }
    }

    public function store(StorePoinSiswaRequest $request): JsonResponse
    {
        try {
            $ta = TahunAjaran::where('is_active', true)->firstOrFail();
            $user = Auth::user();

            $siswa = Siswa::where('id', $request->siswa_id)
                ->where('is_active', true)
                ->whereHas('kelas', fn($q) => $q->where('is_active', true))
                ->first();

            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal: Siswa tidak ditemukan atau kelas sudah tidak aktif.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $poin = DB::transaction(function () use ($request, $ta, $user) {
                return PoinSiswa::create(array_merge($request->validated(), [
                    'tahun_ajaran_id' => $ta->id,
                    'guru_staf_id' => $request->guru_staf_id ?? $user->guru_staf_id
                ]));
            });

            return response()->json([
                'success' => true,
                'message' => 'Poin berhasil dicatat.',
                'data' => new PoinSiswaResource($this->getPoinWithKumulatif($poin->id))
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Admin Poin Store Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mencatat poin.'], 500);
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
                    'message' => 'Gagal: Data tidak dapat diubah karena siswa atau kelas sudah tidak aktif.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            DB::transaction(fn() => $poinSiswa->update($request->validated()));
            
            return response()->json([
                'success' => true,
                'message' => 'Poin diperbarui.',
                'data' => new PoinSiswaResource($this->getPoinWithKumulatif($poinSiswa->id))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Admin Poin Update Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal update.'], 500);
        }
    }

    public function destroy(PoinSiswa $poinSiswa): JsonResponse
    {
        try {
            DB::transaction(fn() => $poinSiswa->delete());
            return response()->json(['success' => true, 'message' => 'Data poin dihapus.'], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Admin Poin Delete Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menghapus data.'], 500);
        }
    }

    public function export(Request $request)
    {
        $profil = DB::table('profil_sekolah')->first();
        $kontak = DB::table('data_kontak')->first();
        
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
            new PoinSiswaExport($query->orderBy('tanggal', 'asc'), $namaKelas, $labelWaktu, $profil, $kontak),
            $fileName
        );
    }
}