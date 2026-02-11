<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\JadwalProduktif;
use App\Http\Resources\JadwalProduktifResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class JadwalProduktifController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Siswa');
    }

    public function index(): JsonResponse
    {
        try {
            $siswa = Auth::user()->siswa()->with('kelas')->first();

            if (!$siswa || !$siswa->kelas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data kelas atau jurusan siswa tidak ditemukan.'
                ], Response::HTTP_NOT_FOUND);
            }

            $perPage = min((int) request()->get('per_page', 20), 100);

            $jadwal = JadwalProduktif::where('jurusan_id', $siswa->kelas->jurusan_id)
                ->with(['jurusan', 'guruStaf'])
                ->latest()
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Daftar jadwal produktif berhasil diambil.',
                'data'    => JadwalProduktifResource::collection($jadwal),
                'meta'    => [
                    'current_page' => $jadwal->currentPage(),
                    'last_page'    => $jadwal->lastPage(),
                    'per_page'     => (int) $jadwal->perPage(),
                    'total'        => $jadwal->total(),
                ],
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data jadwal produktif.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $siswa = Auth::user()->siswa()->with('kelas')->first();
            $jadwal = JadwalProduktif::with(['jurusan', 'guruStaf'])->findOrFail($id);

            if ($jadwal->jurusan_id !== $siswa->kelas->jurusan_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses dilarang. Anda hanya dapat melihat jadwal jurusan sendiri.'
                ], Response::HTTP_FORBIDDEN);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail jadwal produktif berhasil diambil.',
                'data'    => new JadwalProduktifResource($jadwal)
            ], Response::HTTP_OK);
            
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal produktif tidak ditemukan.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_NOT_FOUND);
        }
    }
}