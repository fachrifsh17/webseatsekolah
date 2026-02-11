<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\{Presensi, TahunAjaran};
use App\Http\Resources\PresensiResource;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Auth, Log};
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PresensiController extends Controller
{
    use AuthorizesRequests;

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

            if ($request->filled('tahun_ajaran_id')) {
                $query->where('tahun_ajaran_id', $request->tahun_ajaran_id);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('status', 'like', "%{$search}%")
                      ->orWhere('keterangan', 'like', "%{$search}%")
                      ->orWhere('tanggal', 'like', "%{$search}%");
                });
            }

            if ($request->filled('tanggal')) {
                $query->whereDate('tanggal', $request->tanggal);
            }

            $perPage = min((int) $request->get('per_page', 15), 50);
            
            $data = $query->orderBy('tanggal', 'desc')
                          ->orderBy('created_at', 'desc')
                          ->paginate($perPage);

            return PresensiResource::collection($data)
                ->additional([
                    'success' => true,
                    'message' => 'Data presensi berhasil diambil.'
                ])
                ->response()
                ->setStatusCode(Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Gagal mengambil daftar presensi: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data presensi.',
                'errors'  => ['exception' => [$e->getMessage()]]
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
                    'message' => 'Data tidak ditemukan atau akses dilarang.'
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
            Log::error('Gagal mengambil detail presensi: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail presensi.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}