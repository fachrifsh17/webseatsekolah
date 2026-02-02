<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\PoinSiswa;
use App\Models\TahunAjaran;
use App\Models\Siswa;
use App\Http\Requests\StorePoinSiswaRequest;
use App\Http\Resources\PoinSiswaResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PoinSiswaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PoinSiswa::class);

        $user = Auth::user();
        $guruStafId = $user->guruStaf?->id;

        $query = PoinSiswa::query()
            ->where('guru_staf_id', $guruStafId)
            ->whereHas('siswa', fn($q) => $q->where('is_active', true));

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('siswa', function ($qSiswa) use ($search) {
                    $qSiswa->where('nama_lengkap', 'like', "%{$search}%")
                           ->orWhere('nisn', 'like', "%{$search}%");
                })->orWhere('keterangan', 'like', "%{$search}%");
            });
        }

        if ($request->filled('siswa_id')) {
            $query->where('siswa_id', $request->siswa_id);
        }

        $summaryPersonal = (clone $query)->select(
            DB::raw('SUM(poin_positif) as total_positif'),
            DB::raw('SUM(poin_negatif) as total_negatif'),
            DB::raw('COUNT(*) as total_catatan')
        )->first();

        $summaryKumulatif = null;
        if ($request->filled('siswa_id')) {
            $summaryKumulatif = DB::table('poin_siswa')
                ->where('siswa_id', $request->siswa_id)
                ->select(
                    DB::raw('SUM(poin_positif) as total_plus'),
                    DB::raw('SUM(poin_negatif) as total_minus'),
                    DB::raw('SUM(poin_positif) - SUM(poin_negatif) as saldo_poin')
                )->first();
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $data = $query->with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])
                      ->latest()
                      ->paginate($perPage);

        return response()->json([
            'success' => true,
            'summary_personal' => [
                'total_positif' => (int) ($summaryPersonal->total_positif ?? 0),
                'total_negatif' => (int) ($summaryPersonal->total_negatif ?? 0),
                'total_catatan' => (int) ($summaryPersonal->total_catatan ?? 0),
            ],
            'summary_kumulatif' => $summaryKumulatif,
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
        $tahunAjaran = TahunAjaran::where('is_active', true)->first();

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
                'message' => 'Siswa tidak ditemukan atau status tidak aktif.'
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $validated = $request->validated();
            $validated['guru_staf_id'] = $user->guruStaf->id;
            $validated['tahun_ajaran_id'] = $tahunAjaran->id;

            $poin = PoinSiswa::create($validated);
            
            return response()->json([
                'success' => true,
                'message' => 'Poin siswa berhasil dicatat.',
                'data'    => new PoinSiswaResource($poin->load(['siswa.kelas', 'guruStaf', 'tahunAjaran'])),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan data.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(PoinSiswa $poinSiswa): JsonResponse
    {
        $this->authorize('view', $poinSiswa);

        return response()->json([
            'success' => true,
            'data'    => new PoinSiswaResource($poinSiswa->load(['siswa.kelas', 'guruStaf', 'tahunAjaran'])),
        ], Response::HTTP_OK);
    }
}