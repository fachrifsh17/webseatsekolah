<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\JamSekolah;
use App\Http\Resources\JamSekolahResource;
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
            $data = JamSekolah::with('tahunAjaran')
                ->orderBy('hari')
                ->orderBy('jam_ke')
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