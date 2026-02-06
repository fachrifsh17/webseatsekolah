<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\JamSekolah;
use App\Models\TahunAjaran;
use App\Models\ProfilSekolah;
use App\Models\DataKontak;
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

            $data = JamSekolah::with('tahunAjaran')
                ->where('tahun_ajaran_id', $tahunAktif->id ?? 0)
                ->orderBy('hari')
                ->orderBy('waktu_mulai')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar jam sekolah berhasil diambil.',
                'data'    => JamSekolahResource::collection($data)
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data jam sekolah.',
                'error'   => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export()
    {
        try {
            $profil = ProfilSekolah::first();
            $kontak = DataKontak::first();
            $fileName = 'jadwal_jam_sekolah_' . date('Ymd_His') . '.xlsx';

            return Excel::download(new JamSekolahExport($profil, $kontak), $fileName);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengekspor jadwal.',
                'error'   => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $jamSekolah = JamSekolah::with('tahunAjaran')->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Detail jam sekolah berhasil diambil.',
                'data'    => new JamSekolahResource($jamSekolah)
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data jam sekolah tidak ditemukan.',
                'error'   => $e->getMessage()
            ], Response::HTTP_NOT_FOUND);
        }
    }
}