<?php

namespace App\Http\Controllers\Kesiswaan;

use App\Http\Controllers\Controller;
use App\Models\{PoinSiswa, TahunAjaran, Siswa};
use App\Http\Requests\{StorePoinSiswaRequest, UpdatePoinSiswaRequest};
use App\Http\Resources\PoinSiswaResource;
use App\Exports\PoinSiswaExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Auth, Log};
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PoinSiswaController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);
        $this->authorizeResource(PoinSiswa::class, 'poin_siswa');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = PoinSiswa::with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])
                ->whereHas('siswa', function ($q) {
                    $q->where('is_active', true)
                      ->whereHas('kelas', fn($qk) => $qk->where('is_active', true));
                });

            if ($request->filled('siswa_id')) {
                $query->where('siswa_id', $request->siswa_id);
            }

            if ($request->filled('tahun_ajaran_id')) {
                $query->where('tahun_ajaran_id', $request->tahun_ajaran_id);
            }

            if ($request->filled('bulan')) {
                $date = Carbon::parse($request->bulan);
                $query->whereMonth('tanggal', $date->month)
                      ->whereYear('tanggal', $date->year);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('siswa', function ($q) use ($search) {
                    $q->where('nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('nisn', 'like', "%{$search}%");
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

            $perPage = min((int) $request->get('per_page', 20), 100);
            $data = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'summary_kumulatif' => $summaryGlobal,
                'data'    => PoinSiswaResource::collection($data),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'per_page'     => $data->perPage(),
                    'total'        => $data->total(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Kesiswaan Poin Index Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengambil data.'], 500);
        }
    }

    public function store(StorePoinSiswaRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $taActive = TahunAjaran::where('is_active', true)->first();

            if (!$taActive) {
                return response()->json(['success' => false, 'message' => 'Tahun ajaran aktif tidak ditemukan.'], 422);
            }

            $siswa = Siswa::where('id', $request->siswa_id)
                ->where('is_active', true)
                ->whereHas('kelas', fn($q) => $q->where('is_active', true))
                ->first();

            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak ditemukan atau kelas tidak aktif.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $poin = DB::transaction(function () use ($request, $user, $taActive) {
                return PoinSiswa::create(array_merge($request->validated(), [
                    'guru_staf_id' => $request->guru_staf_id ?? $user->guruStaf?->id,
                    'tahun_ajaran_id' => $taActive->id,
                ]));
            });

            return response()->json([
                'success' => true,
                'message' => 'Poin siswa berhasil dicatat.',
                'data'    => new PoinSiswaResource($poin->load(['siswa.kelas', 'guruStaf', 'tahunAjaran']))
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Kesiswaan Poin Store Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data.'], 500);
        }
    }

    public function show(PoinSiswa $poinSiswa): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new PoinSiswaResource($poinSiswa->load(['siswa.kelas', 'guruStaf', 'tahunAjaran']))
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
                'data'    => new PoinSiswaResource($poinSiswa->fresh(['siswa.kelas', 'guruStaf', 'tahunAjaran']))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Kesiswaan Poin Update Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui data.'], 500);
        }
    }

    public function destroy(PoinSiswa $poinSiswa): JsonResponse
    {
        try {
            DB::transaction(fn() => $poinSiswa->delete());
            return response()->json(['success' => true, 'message' => 'Data berhasil dihapus.'], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Kesiswaan Poin Destroy Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menghapus data.'], 500);
        }
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', PoinSiswa::class);

        $profil = DB::table('profil_sekolah')->first();
        $kontak = DB::table('data_kontak')->first();

        $query = PoinSiswa::with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])
            ->whereHas('siswa', function($q) {
                $q->where('is_active', true)
                  ->whereHas('kelas', fn($qk) => $qk->where('is_active', true));
            })
            ->orderBy('tanggal', 'asc');

        if ($request->filled('kelas_id')) {
            $query->whereHas('siswa', fn($q) => $q->where('kelas_id', $request->kelas_id));
        }

        if ($request->filled('tahun_ajaran_id')) {
            $query->where('tahun_ajaran_id', $request->tahun_ajaran_id);
        }

        $bulanFilter = 'Semua_Waktu';
        $labelWaktu = "Seluruh Periode (Kumulatif)";

        if ($request->filled('bulan')) {
            $date = Carbon::parse($request->bulan);
            $query->whereMonth('tanggal', $date->month)->whereYear('tanggal', $date->year);
            $labelWaktu = $date->translatedFormat('F Y');
            $bulanFilter = $date->format('M_Y');
        }

        $namaKelas = $request->nama_kelas ?? 'Seluruh Siswa';
        $namaKelasFile = str_replace([' ', '/', '\\'], '_', $namaKelas);
        $fileName = "Rekap_Poin_{$namaKelasFile}_{$bulanFilter}_" . now()->format('His') . ".xlsx";

        return Excel::download(
            new PoinSiswaExport($query, $namaKelas, $labelWaktu, $profil, $kontak),
            $fileName
        );
    }
}