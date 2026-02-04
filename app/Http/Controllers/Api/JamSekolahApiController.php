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
            $data = JamSekolah::with('tahunAjaran')
                ->whereHas('tahunAjaran', function ($query) {
                    $query->where('is_active', true);
                })
                ->orderBy('hari')
                ->orderBy('jam_ke')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data jam sekolah aktif berhasil diambil',
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
            $jamSekolah = JamSekolah::with('tahunAjaran')->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Detail jam sekolah ditemukan',
                'data'    => new JamSekolahResource($jamSekolah)
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data jam sekolah tidak ditemukan',
            ], Response::HTTP_NOT_FOUND);
        }
    }
}