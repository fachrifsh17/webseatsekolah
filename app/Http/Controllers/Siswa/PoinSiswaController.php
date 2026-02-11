<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\{PoinSiswa, TahunAjaran};
use App\Http\Resources\PoinSiswaResource;
use Illuminate\Http\{JsonResponse, Request};
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
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', PoinSiswa::class);

            $siswa = Auth::user()->siswa;
            if (!$siswa || !$siswa->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data siswa tidak ditemukan atau akun sudah tidak aktif.'
                ], Response::HTTP_NOT_FOUND);
            }

            $query = PoinSiswa::query()->where('siswa_id', $siswa->id);

            // Filter Pencarian
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('keterangan', 'like', "%{$search}%")
                      ->orWhereHas('guruStaf', function($qg) use ($search) {
                          $qg->where('nama_lengkap', 'like', "%{$search}%");
                      });
                });
            }

            // Filter Tanggal
            if ($request->filled('mulai_tanggal') && $request->filled('sampai_tanggal')) {
                $query->whereBetween('tanggal', [$request->mulai_tanggal, $request->sampai_tanggal]);
            }

            // Filter Jenis Poin
            if ($request->filled('jenis')) {
                if ($request->jenis === 'negatif') {
                    $query->where('poin_negatif', '>', 0);
                } elseif ($request->jenis === 'positif') {
                    $query->where('poin_positif', '>', 0);
                }
            }

            // Kalkulasi Summary
            $summary = (clone $query)->select(
                DB::raw('CAST(SUM(poin_positif) AS SIGNED) as total_plus'),
                DB::raw('CAST(SUM(poin_negatif) AS SIGNED) as total_minus'),
                DB::raw('CAST(SUM(poin_positif - poin_negatif) AS SIGNED) as saldo_akumulasi'),
                DB::raw('COUNT(*) as total_catatan')
            )->first();

            $tahunAktif = TahunAjaran::where('is_active', true)->first();
            $data = $query->with(['guruStaf', 'tahunAjaran', 'siswa.kelas'])
                          ->latest()
                          ->paginate(min((int) $request->get('per_page', 10), 100));

            return response()->json([
                'success' => true,
                'summary_kumulatif' => $summary,
                'tahun_ajaran_aktif' => $tahunAktif,
                'data'    => PoinSiswaResource::collection($data),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'per_page'     => (int) $data->perPage(),
                    'total'        => $data->total(),
                ],
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Gagal mengambil poin siswa: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data poin.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(PoinSiswa $poinSiswa): JsonResponse
    {
        try {
            $this->authorize('view', $poinSiswa);

            return response()->json([
                'success' => true,
                'data'    => new PoinSiswaResource($poinSiswa->load(['guruStaf', 'tahunAjaran', 'siswa.kelas'])),
            ], Response::HTTP_OK);
            
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data poin tidak ditemukan atau akses dilarang.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_FORBIDDEN);
        }
    }
}