<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PoinSiswa;
use App\Models\TahunAjaran;
use App\Models\Siswa;
use App\Http\Requests\StorePoinSiswaRequest;
use App\Http\Requests\UpdatePoinSiswaRequest;
use App\Http\Resources\PoinSiswaResource;
use App\Exports\PoinSiswaExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
        $this->authorize('viewAny', PoinSiswa::class);

        $query = PoinSiswa::with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])
            ->whereHas('siswa', function ($q) {
                $q->where('is_active', true);
            });

        if ($request->filled('siswa_id')) {
            $query->where('siswa_id', $request->siswa_id);
        }

        if ($request->filled('tahun_ajaran_id')) {
            $query->where('tahun_ajaran_id', $request->tahun_ajaran_id);
        }

        if ($request->filled('bulan')) {
            $query->whereMonth('tanggal', date('m', strtotime($request->bulan)))
                  ->whereYear('tanggal', date('Y', strtotime($request->bulan)));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('siswa', function ($q) use ($search) {
                $q->where(function($sq) use ($search) {
                    $sq->where('nama_lengkap', 'like', "%{$search}%")
                       ->orWhere('nisn', 'like', "%{$search}%");
                });
            });
        }

        $query->orderByDesc('tanggal')->orderByDesc('created_at');

        $summaryGlobal = null;
        if ($request->filled('siswa_id')) {
            $summaryGlobal = DB::table('poin_siswa')
                ->where('siswa_id', $request->siswa_id)
                ->select(
                    DB::raw('SUM(poin_positif) as total_plus'),
                    DB::raw('SUM(poin_negatif) as total_minus'),
                    DB::raw('SUM(poin_positif) - SUM(poin_negatif) as saldo_poin')
                )->first();
        }

        $perPage = $request->get('per_page', 20);
        $data = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'summary_kumulatif' => $summaryGlobal,
            'data'    => PoinSiswaResource::collection($data),
            'meta'    => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'per_page'     => (int) $data->perPage(),
                'total'        => $data->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePoinSiswaRequest $request): JsonResponse
    {
        $this->authorize('create', PoinSiswa::class);

        $user = Auth::user();
        $tahunAjaran = TahunAjaran::where('is_active', 1)->first();

        if (!$tahunAjaran) {
            return response()->json([
                'success' => false,
                'message' => 'Tahun ajaran aktif tidak ditemukan.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $siswa = Siswa::where('id', $request->siswa_id)
            ->where('is_active', true)
            ->first();

        if (!$siswa) {
            return response()->json([
                'success' => false,
                'message' => 'Siswa tidak ditemukan atau sudah tidak aktif.'
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();
        $validated['guru_staf_id'] = $request->guru_staf_id ?? $user->guruStaf?->id;
        $validated['tahun_ajaran_id'] = $tahunAjaran->id;

        try {
            $poin = DB::transaction(fn () => PoinSiswa::create($validated));
            return response()->json([
                'success' => true,
                'message' => 'Poin berhasil dicatat.',
                'data'    => new PoinSiswaResource($poin->load(['siswa.kelas', 'guruStaf', 'tahunAjaran'])),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Admin Store Poin Error', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mencatat poin.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', PoinSiswa::class);

        $profil = DB::table('profil_sekolah')->first();
        $kontak = DB::table('data_kontak')->first();

        $query = PoinSiswa::with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])
            ->whereHas('siswa', function ($q) {
                $q->where('is_active', true);
            })
            ->orderBy('tanggal', 'asc');

        if ($request->filled('kelas_id')) {
            $query->whereHas('siswa', function ($q) use ($request) {
                $q->where('kelas_id', $request->kelas_id);
            });
        }

        if ($request->filled('tahun_ajaran_id')) {
            $query->where('tahun_ajaran_id', $request->tahun_ajaran_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('siswa', function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%");
            });
        }

        if ($request->filled('bulan')) {
            $query->whereMonth('tanggal', date('m', strtotime($request->bulan)))
                  ->whereYear('tanggal', date('Y', strtotime($request->bulan)));
            $labelWaktu = date('F Y', strtotime($request->bulan));
        } else {
            $labelWaktu = "Seluruh Periode (Kumulatif)";
        }

        $namaKelas = $request->nama_kelas ?? 'Seluruh Siswa';

        return Excel::download(
            new PoinSiswaExport($query, $namaKelas, $labelWaktu, $profil, $kontak),
            "Rekap_Poin_Siswa_" . now()->format('YmdHis') . ".xlsx"
        );
    }

    public function show($id): JsonResponse
    {
        $poin = PoinSiswa::with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])->findOrFail($id);
        $this->authorize('view', $poin);

        return response()->json([
            'success' => true,
            'data'    => new PoinSiswaResource($poin),
        ], Response::HTTP_OK);
    }

    public function update(UpdatePoinSiswaRequest $request, $id): JsonResponse
    {
        $poin = PoinSiswa::findOrFail($id);
        $this->authorize('update', $poin);

        try {
            DB::transaction(fn () => $poin->update($request->validated()));
            return response()->json([
                'success' => true,
                'message' => 'Data diperbarui.',
                'data'    => new PoinSiswaResource($poin->fresh(['siswa.kelas', 'guruStaf', 'tahunAjaran'])),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Admin Update Poin Error', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy($id): JsonResponse
    {
        $poin = PoinSiswa::findOrFail($id);
        $this->authorize('delete', $poin);

        try {
            DB::transaction(fn () => $poin->delete());
            return response()->json([
                'success' => true,
                'message' => 'Data berhasil dihapus.'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Admin Delete Poin Error', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}