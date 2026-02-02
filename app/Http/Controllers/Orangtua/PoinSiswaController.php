<?php

namespace App\Http\Controllers\Orangtua;

use App\Http\Controllers\Controller;
use App\Models\PoinSiswa;
use App\Models\TahunAjaran;
use App\Http\Resources\PoinSiswaResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
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
        
        $childrenIds = DB::table('orangtua_siswa')
            ->join('orangtua', 'orangtua_siswa.orangtua_id', '=', 'orangtua.id')
            ->join('siswa', 'orangtua_siswa.siswa_id', '=', 'siswa.id')
            ->where('orangtua.user_id', $user->id)
            ->where('siswa.is_active', true)
            ->pluck('orangtua_siswa.siswa_id')
            ->toArray();

        if (empty($childrenIds)) {
            return response()->json([
                'success' => true,
                'summary' => null,
                'data'    => [],
                'meta'    => null
            ], Response::HTTP_OK);
        }

        $tahunAktif = TahunAjaran::where('is_active', true)->first();

        $query = PoinSiswa::query()->whereIn('siswa_id', $childrenIds);

        if ($request->filled('siswa_id')) {
            $query->where('siswa_id', $request->siswa_id);
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

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('keterangan', 'like', "%{$search}%")
                  ->orWhereHas('siswa', function($qs) use ($search) {
                      $qs->where('nama_lengkap', 'like', "%{$search}%");
                  });
            });
        }

        $summary = (clone $query)->select(
                DB::raw('CAST(SUM(poin_positif) AS SIGNED) as total_plus'),
                DB::raw('CAST(SUM(poin_negatif) AS SIGNED) as total_minus'),
                DB::raw('CAST(SUM(poin_positif - poin_negatif) AS SIGNED) as saldo_akumulasi'),
                DB::raw('COUNT(*) as total_catatan')
            )
            ->first();

        $data = $query->with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])
            ->latest()
            ->paginate($request->get('per_page', 20));

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

        $user = Auth::user();
        $isOwnChild = DB::table('orangtua_siswa')
            ->join('orangtua', 'orangtua_siswa.orangtua_id', '=', 'orangtua.id')
            ->where('orangtua.user_id', $user->id)
            ->where('orangtua_siswa.siswa_id', $poinSiswa->siswa_id)
            ->exists();

        if (!$isOwnChild) {
            return response()->json([
                'success' => false,
                'message' => 'Akses dilarang.'
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'success' => true,
            'data'    => new PoinSiswaResource($poinSiswa->load(['siswa.kelas', 'guruStaf', 'tahunAjaran'])),
        ], Response::HTTP_OK);
    }
}