<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JamSekolah;
use App\Http\Resources\JamSekolahResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class JamSekolahApiController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            // Filter berdasarkan Semester Aktif yang terhubung ke Tahun Ajaran Aktif
            $data = JamSekolah::with(['semester.tahunAjaran'])
                ->whereHas('semester', function ($query) {
                    $query->where('is_active', true)
                          ->whereHas('tahunAjaran', function ($q) {
                              $q->where('is_active', true);
                          });
                })
                ->orderBy('hari')
                ->orderBy('jam_ke')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data jam sekolah semester aktif berhasil diambil',
                'data'    => JamSekolahResource::collection($data)
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data jam sekolah',
                'error'   => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            // Memastikan data yang dicari juga berada dalam lingkup semester aktif
            $jamSekolah = JamSekolah::with(['semester.tahunAjaran'])
                ->whereHas('semester', function ($query) {
                    $query->where('is_active', true)
                          ->whereHas('tahunAjaran', function ($q) {
                              $q->where('is_active', true);
                          });
                })
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Detail jam sekolah ditemukan',
                'data'    => new JamSekolahResource($jamSekolah)
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data jam sekolah tidak ditemukan atau tidak aktif pada periode ini',
            ], Response::HTTP_NOT_FOUND);
        }
    }
}