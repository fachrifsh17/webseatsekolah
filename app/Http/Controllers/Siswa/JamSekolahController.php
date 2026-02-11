<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\{JamSekolah, TahunAjaran, ProfilSekolah, DataKontak};
use App\Http\Resources\JamSekolahResource;
use App\Exports\JamSekolahExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class JamSekolahController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Siswa');
    }

    public function index(): JsonResponse
    {
        try {
            $tahunAktif = TahunAjaran::where('is_active', true)->first();

            if (!$tahunAktif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada Tahun Ajaran yang aktif saat ini.',
                ], Response::HTTP_NOT_FOUND);
            }

            $data = JamSekolah::with('tahunAjaran')
                ->where('tahun_ajaran_id', $tahunAktif->id)
                ->orderBy('hari')
                ->orderBy('waktu_mulai')
                ->get();

            return response()->json([
                'success' => true,
                'data'    => JamSekolahResource::collection($data),
                'meta'    => [
                    'tahun_ajaran' => $tahunAktif->nama
                ]
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data jam sekolah.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export()
    {
        try {
            $tahunAktif = TahunAjaran::where('is_active', true)->first();

            if (!$tahunAktif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal ekspor: Tahun ajaran aktif tidak ditemukan.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $profil = ProfilSekolah::first();
            $kontak = DataKontak::first();
            
            $namaTA = str_replace(['/', '\\', ' '], '-', $tahunAktif->nama);
            $fileName = 'jam_sekolah_' . $namaTA . '.xlsx';

            return Excel::download(new JamSekolahExport($profil, $kontak, $tahunAktif->id), $fileName);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengekspor jadwal.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $tahunAktif = TahunAjaran::where('is_active', true)->first();

            $jamSekolah = JamSekolah::with('tahunAjaran')
                ->where('tahun_ajaran_id', $tahunAktif->id ?? 0)
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data'    => new JamSekolahResource($jamSekolah)
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data jam sekolah tidak ditemukan atau tidak aktif.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_NOT_FOUND);
        }
    }
}