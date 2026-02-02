<?php

namespace App\Http\Controllers\OrangTua;

use App\Http\Controllers\Controller;
use App\Models\Presensi;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Http\Resources\PresensiResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PresensiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
    }

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $orangtua = DB::table('orangtua')->where('user_id', $user->id)->first();

        if (!$orangtua) {
            return response()->json(['success' => true, 'summary' => [], 'data' => []], Response::HTTP_OK);
        }

        $siswaIds = DB::table('orangtua_siswa')
            ->where('orangtua_id', $orangtua->id)
            ->pluck('siswa_id')
            ->toArray();

        if (empty($siswaIds)) {
            return response()->json(['success' => true, 'summary' => [], 'data' => []], Response::HTTP_OK);
        }

        if ($request->filled('tahun_ajaran_id')) {
            $tahunAjaranId = $request->tahun_ajaran_id;
        } else {
            $tahunAktif = TahunAjaran::where('is_active', true)->first();
            $tahunAjaranId = $tahunAktif?->id;
        }

        $baseQuery = Presensi::whereIn('siswa_id', $siswaIds);

        if (!$request->filled('tahun_ajaran_id')) {
            $baseQuery->whereHas('siswa.kelas', fn($q) => $q->where('is_active', true));
        }

        $summaryQuery = clone $baseQuery;
        
        if ($request->filled('siswa_id')) {
            $summaryQuery->where('siswa_id', $request->siswa_id);
        }

        if ($tahunAjaranId) {
            $summaryQuery->where('tahun_ajaran_id', $tahunAjaranId);
        }

        $summary = $summaryQuery->select(
                DB::raw("SUM(CASE WHEN status = 'Hadir' THEN 1 ELSE 0 END) as hadir"),
                DB::raw("SUM(CASE WHEN status = 'Izin' THEN 1 ELSE 0 END) as izin"),
                DB::raw("SUM(CASE WHEN status = 'Sakit' THEN 1 ELSE 0 END) as sakit"),
                DB::raw("SUM(CASE WHEN status = 'Alpa' THEN 1 ELSE 0 END) as alpa"),
                DB::raw("COUNT(*) as total_hari")
            )
            ->first();

        $query = clone $baseQuery;
        $query->with(['siswa.kelas', 'guruStaf', 'tahunAjaran']);

        if ($request->filled('siswa_id')) {
            $query->where('siswa_id', $request->siswa_id);
        }

        if ($tahunAjaranId) {
            $query->where('tahun_ajaran_id', $tahunAjaranId);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('siswa', fn($qs) => $qs->where('nama_lengkap', 'like', "%{$search}%"))
                  ->orWhere('status', 'like', "%{$search}%")
                  ->orWhere('keterangan', 'like', "%{$search}%");
            });
        }

        if ($request->filled('mulai_tanggal') && $request->filled('sampai_tanggal')) {
            $query->whereBetween('tanggal', [$request->mulai_tanggal, $request->sampai_tanggal]);
        }

        $perPage = $request->get('per_page', 15);
        $data = $query->orderBy('tanggal', 'desc')
                      ->orderBy('created_at', 'desc')
                      ->paginate($perPage);

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'tahun_ajaran_pilihan' => $tahunAjaranId,
            'data'    => PresensiResource::collection($data),
            'meta'    => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'per_page'     => (int) $data->perPage(),
                'total'        => $data->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function listAnak(): JsonResponse
    {
        $user = Auth::user();
        $orangtua = DB::table('orangtua')->where('user_id', $user->id)->first();

        if (!$orangtua) {
            return response()->json(['success' => true, 'data' => []], Response::HTTP_OK);
        }

        $anak = Siswa::whereIn('id', function($query) use ($orangtua) {
                $query->select('siswa_id')
                    ->from('orangtua_siswa')
                    ->where('orangtua_id', $orangtua->id);
            })
            ->with('kelas')
            ->get();

        return response()->json(['success' => true, 'data' => $anak], Response::HTTP_OK);
    }
}