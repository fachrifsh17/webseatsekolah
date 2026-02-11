<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\{PoinSiswa, TahunAjaran, Siswa};
use App\Http\Requests\StorePoinSiswaRequest;
use App\Http\Resources\PoinSiswaResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Log};
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PoinSiswaController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        // Menggunakan middleware log khusus untuk pencatatan poin
        $this->middleware('log.aktivitas')->only('store');
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PoinSiswa::class);

        $user = Auth::user();
        $guruStafId = $user->guruStaf?->id;

        if (!$guruStafId) {
            return response()->json([
                'success' => false, 
                'message' => 'Profil guru tidak ditemukan.'
            ], Response::HTTP_FORBIDDEN);
        }

        $query = PoinSiswa::query()
            ->where('guru_staf_id', $guruStafId)
            ->whereHas('siswa', function($q) {
                $q->where('is_active', true)
                  ->whereHas('kelas', fn($qk) => $qk->where('is_active', true));
            });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('siswa', function ($qS) use ($search) {
                    $qS->where('nama_lengkap', 'like', "%{$search}%")
                       ->orWhere('nisn', 'like', "%{$search}%");
                })->orWhere('keterangan', 'like', "%{$search}%");
            });
        }

        if ($request->filled('siswa_id')) {
            $query->where('siswa_id', $request->siswa_id);
        }

        // Statistik poin yang diberikan oleh guru yang sedang login
        $summaryPersonal = (clone $query)->select(
            DB::raw('SUM(poin_positif) as total_positif'),
            DB::raw('SUM(poin_negatif) as total_negatif'),
            DB::raw('COUNT(*) as total_catatan')
        )->first();

        // Statistik kumulatif siswa (semua poin dari semua guru) jika filter siswa_id aktif
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
        $data = $query->with(['siswa.kelas', 'tahunAjaran'])
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
            'data' => PoinSiswaResource::collection($data),
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'total'        => $data->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePoinSiswaRequest $request): JsonResponse
    {
        $this->authorize('create', PoinSiswa::class);

        try {
            $user = Auth::user();
            $taActive = TahunAjaran::where('is_active', true)->first();

            if (!$taActive) {
                return response()->json(['success' => false, 'message' => 'Tahun ajaran aktif tidak ditemukan.'], Response::HTTP_NOT_FOUND);
            }

            if (!$user->guruStaf) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
            }

            $siswa = Siswa::where('id', $request->siswa_id)
                ->where('is_active', true)
                ->whereHas('kelas', fn($q) => $q->where('is_active', true))
                ->first();

            if (!$siswa) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Gagal: Siswa tidak ditemukan atau berada di kelas yang sudah tidak aktif.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $poin = DB::transaction(function () use ($request, $user, $taActive) {
                return PoinSiswa::create(array_merge($request->validated(), [
                    'guru_staf_id' => $user->guruStaf->id,
                    'tahun_ajaran_id' => $taActive->id,
                ]));
            });

            return response()->json([
                'success' => true,
                'message' => 'Poin siswa berhasil dicatat.',
                'data' => new PoinSiswaResource($poin->load(['siswa.kelas', 'tahunAjaran'])),
            ], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            Log::error('Guru Poin Store Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(PoinSiswa $poinSiswa): JsonResponse
    {
        $this->authorize('view', $poinSiswa);

        // Proteksi agar Guru hanya bisa melihat detail poin yang ia buat sendiri
        if ($poinSiswa->guru_staf_id !== Auth::user()->guruStaf?->id) {
            return response()->json([
                'success' => false, 
                'message' => 'Anda tidak memiliki akses ke data ini.'
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'success' => true,
            'data' => new PoinSiswaResource($poinSiswa->load(['siswa.kelas', 'tahunAjaran'])),
        ], Response::HTTP_OK);
    }
}