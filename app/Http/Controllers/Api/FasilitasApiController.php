<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fasilitas;
use App\Http\Resources\FasilitasResource;
use Symfony\Component\HttpFoundation\Response;

class FasilitasApiController extends Controller
{
    public function index()
    {
        try {
            // Mengganti get() menjadi paginate()
            // Kita set 10 data per halaman (bisa kamu sesuaikan)
            $data = Fasilitas::orderBy('id', 'desc')->paginate(10);

            return FasilitasResource::collection($data)->additional([
                'success' => true,
                'message' => 'Daftar fasilitas berhasil diambil'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data fasilitas',
                'error'   => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        try {
            $fasilitas = Fasilitas::findOrFail($id);
            
            return (new FasilitasResource($fasilitas))->additional([
                'success' => true,
                'message' => 'Detail fasilitas ditemukan'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fasilitas tidak ditemukan',
            ], Response::HTTP_NOT_FOUND);
        }
    }
}