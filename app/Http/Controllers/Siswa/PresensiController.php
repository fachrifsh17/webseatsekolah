<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\{Presensi, TahunAjaran};
use App\Http\Resources\PresensiResource;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PresensiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Siswa');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $siswaId = $user->siswa_id ?? $user->siswa?->id;

            if (!$siswaId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data profil siswa tidak ditemukan.'
                ], Response::HTTP_NOT_FOUND);
            }

            $query = Presensi::where('siswa_id', $siswaId)
                ->with(['siswa.kelas', 'guruStaf', 'tahunAjaran']);

            // Filter Tahun Ajaran
            if ($request->filled('tahun_ajaran_id')) {
                $query->where('tahun_ajaran_id', $request->tahun_ajaran_id);
            }

            // Filter Search
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('status', 'like', "%{$search}%")
                      ->orWhere('keterangan', 'like', "%{$search}%")
                      ->orWhere('tanggal', 'like', "%{$search}%");
                });
            }

            // Filter Tanggal
            if ($request->filled('tanggal')) {
                $query->whereDate('tanggal', $request->tanggal);
            }

            $perPage = min((int) $request->get('per_page', 15), 50);
            
            $data = $query->orderBy('tanggal', 'desc')
                          ->orderBy('created_at', 'desc')
                          ->paginate($perPage);

            // Perbaikan struktur return agar tidak double "data"
            return PresensiResource::collection($data)
                ->additional([
                    'success' => true,
                    'message' => 'Data presensi berhasil diambil.'
                ])
                ->response()
                ->setStatusCode(Response::HTTP_OK);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $siswaId = $user->siswa_id ?? $user->siswa?->id;

            $presensi = Presensi::where('id', $id)
                ->where('siswa_id', $siswaId)
                ->first();

            if (!$presensi) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Data tidak ditemukan.'
                ], Response::HTTP_FORBIDDEN);
            }

            return (new PresensiResource($presensi->load(['siswa.kelas', 'guruStaf', 'tahunAjaran'])))
                ->additional([
                    'success' => true,
                    'message' => 'Detail presensi berhasil diambil.'
                ])
                ->response()
                ->setStatusCode(Response::HTTP_OK);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}