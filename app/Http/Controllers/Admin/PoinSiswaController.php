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

    public function index(Request $request): JsonResponse
    {
        try {
            $query = PoinSiswa::with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])
                ->whereHas('siswa', fn($q) => $q->where('is_active', true));

            if ($request->filled('siswa_id')) {
                $query->where('siswa_id', $request->siswa_id);
            }

            if ($request->filled('tahun_ajaran_id')) {
                $query->where('tahun_ajaran_id', $request->tahun_ajaran_id);
            }
            
            if ($request->filled('bulan')) {
                $time = strtotime($request->bulan);
                $query->whereMonth('tanggal', date('m', $time))->whereYear('tanggal', date('Y', $time));
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('siswa', fn($q) => $q->where('nama_lengkap', 'like', "%{$search}%")->orWhere('nisn', 'like', "%{$search}%"));
            }

            $summary = null;
            if ($request->filled('siswa_id')) {
                $summary = DB::table('poin_siswa')
                    ->where('siswa_id', $request->siswa_id)
                    ->select(
                        DB::raw('SUM(poin_positif) as total_plus'),
                        DB::raw('SUM(poin_negatif) as total_minus'),
                        DB::raw('SUM(poin_positif) - SUM(poin_negatif) as saldo_poin')
                    )->first();
            }

            $perPage = min((int) $request->get('per_page', 20), 100);
            $data = $query->orderByDesc('tanggal')->orderByDesc('created_at')->paginate($perPage);

            return response()->json([
                'success' => true,
                'summary_kumulatif' => $summary,
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

            $data = DB::transaction(function () use ($request, $ta, $user) {
                return PoinSiswa::create(array_merge($request->validated(), [
                    'tahun_ajaran_id' => $ta->id,
                    'guru_staf_id' => $request->guru_staf_id ?? $user->guru_staf_id
                ]));
            });

            return response()->json([
                'success' => true,
                'message' => 'Poin berhasil dicatat.',
                'data' => new PoinSiswaResource($data->load(['siswa.kelas', 'guruStaf', 'tahunAjaran']))
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
            'data' => new PoinSiswaResource($poinSiswa->load(['siswa.kelas', 'guruStaf', 'tahunAjaran']))
        ], Response::HTTP_OK);
    }

    public function update(UpdatePoinSiswaRequest $request, PoinSiswa $poinSiswa): JsonResponse
    {
        try {
            DB::transaction(fn() => $poinSiswa->update($request->validated()));
            return response()->json([
                'success' => true,
                'message' => 'Poin diperbarui.',
                'data' => new PoinSiswaResource($poinSiswa->fresh(['siswa.kelas', 'guruStaf', 'tahunAjaran']))
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
        
        $labelWaktu = "Kumulatif";
        $bulanFile = "Semua_Waktu";

        if ($request->filled('bulan')) {
            $time = strtotime($request->bulan);
            $labelWaktu = date('F Y', $time);
            $bulanFile = date('M_Y', $time);
        }

        $query = PoinSiswa::with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])
            ->whereHas('siswa', fn($q) => $q->where('is_active', true));

        if ($request->filled('kelas_id')) {
            $query->whereHas('siswa', fn($q) => $q->where('kelas_id', $request->kelas_id));
        }

        if ($request->filled('tahun_ajaran_id')) {
            $query->where('tahun_ajaran_id', $request->tahun_ajaran_id);
        }

        $fileName = "Rekap_Poin_{$namaKelasFile}_{$bulanFile}_" . date('His') . ".xlsx";

        return Excel::download(
            new PoinSiswaExport($query->orderBy('tanggal', 'asc'), $namaKelas, $labelWaktu, $profil, $kontak),
            $fileName
        );
    }
}