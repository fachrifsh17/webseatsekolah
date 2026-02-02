<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Presensi;
use App\Models\TahunAjaran;
use App\Http\Resources\PresensiResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PresensiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Siswa');
    }

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = Presensi::where('siswa_id', $user->siswa_id)
            ->with(['siswa.kelas', 'guruStaf', 'tahunAjaran']);

        if ($request->filled('tahun_ajaran_id')) {
            $tahunAjaranId = $request->tahun_ajaran_id;
        } else {
            $tahunAktif = TahunAjaran::where('is_active', true)->first();
            $tahunAjaranId = $tahunAktif?->id;
        }

        if (!$request->filled('tahun_ajaran_id')) {
            $query->whereHas('siswa.kelas', fn($q) => $q->where('is_active', true));
        }

        if ($tahunAjaranId) {
            $query->where('tahun_ajaran_id', $tahunAjaranId);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('status', 'like', "%{$search}%")
                  ->orWhere('keterangan', 'like', "%{$search}%")
                  ->orWhere('tanggal', 'like', "%{$search}%")
                  ->orWhereHas('guruStaf', function($qg) use ($search) {
                      $qg->where('nama_lengkap', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('mulai_tanggal') && $request->filled('sampai_tanggal')) {
            $query->whereBetween('tanggal', [$request->mulai_tanggal, $request->sampai_tanggal]);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min((int) $request->get('per_page', 15), 50);
        $data = $query->orderBy('tanggal', 'desc')
                      ->orderBy('created_at', 'desc')
                      ->paginate($perPage);

        return response()->json([
            'success' => true,
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

    public function show(Presensi $presensi): JsonResponse
    {
        if ((string) $presensi->siswa_id !== (string) Auth::user()->siswa_id) {
            return response()->json([
                'success' => false, 
                'message' => 'Akses dilarang.'
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'success' => true,
            'data' => new PresensiResource($presensi->load(['siswa.kelas', 'guruStaf', 'tahunAjaran']))
        ], Response::HTTP_OK);
    }
}