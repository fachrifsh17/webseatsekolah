<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\PoinSiswa;
use App\Models\TahunAjaran;
use App\Http\Resources\PoinSiswaResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

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
        $siswa = $user->siswa;
        
        if (!$siswa || !$siswa->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Data siswa tidak ditemukan atau akun sudah tidak aktif.'
            ], Response::HTTP_NOT_FOUND);
        }

        $tahunAktif = TahunAjaran::where('is_active', true)->first();

        $query = PoinSiswa::query()->where('siswa_id', $siswa->id);

        if ($request->filled('search')) {
            $search = $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('keterangan', 'like', "%{$search}%")
                  ->orWhereHas('guruStaf', function($qg) use ($search) {
                      $qg->where('nama_lengkap', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('mulai_tanggal') && $request->filled('sampai_tanggal')) {
            $query->whereBetween('tanggal', [$request->mulai_tanggal, $request->sampai_tanggal]);
        }

        if ($request->filled('jenis')) {
            if ($request->jenis == 'negatif') {
                $query->where('poin_negatif', '>', 0);
            } elseif ($request->jenis == 'positif') {
                $query->where('poin_positif', '>', 0);
            }
        }

        $summary = (clone $query)->select(
            DB::raw('CAST(SUM(poin_positif) AS SIGNED) as total_plus'),
            DB::raw('CAST(SUM(poin_negatif) AS SIGNED) as total_minus'),
            DB::raw('CAST(SUM(poin_positif - poin_negatif) AS SIGNED) as saldo_akumulasi'),
            DB::raw('COUNT(*) as total_catatan')
        )->first();

        $data = $query->with(['guruStaf', 'tahunAjaran', 'siswa.kelas'])
                      ->latest()
                      ->paginate($request->get('per_page', 10));

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
    }

    public function show(PoinSiswa $poinSiswa): JsonResponse
    {
        $this->authorize('view', $poinSiswa);

        if ($poinSiswa->siswa_id !== Auth::user()->siswa?->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses dilarang.'
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'success' => true,
            'data'    => new PoinSiswaResource($poinSiswa->load(['guruStaf', 'tahunAjaran', 'siswa.kelas'])),
        ], Response::HTTP_OK);
    }
}