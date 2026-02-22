<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jurusan;
use App\Http\Resources\JurusanResource; // 1. Import Resource
use Symfony\Component\HttpFoundation\Response;

class JurusanApiController extends Controller
{
    public function index()
    {
        // 2. Filter hanya yang is_active saja
        $data = Jurusan::where('is_active', true)
            ->orderBy('nama_jurusan')
            ->get();

        // 3. Kembalikan menggunakan Resource::collection
        return JurusanResource::collection($data)->additional([
            'success' => true,
            'message' => 'Daftar jurusan aktif berhasil dimuat'
        ]);
    }

    public function show($id)
    {
        // 4. Pastikan detail juga hanya bisa melihat yang aktif
        $jurusan = Jurusan::where('is_active', true)->find($id);

        if (!$jurusan) {
            return response()->json([
                'success' => false,
                'message' => 'Jurusan tidak ditemukan atau tidak aktif'
            ], Response::HTTP_NOT_FOUND);
        }

        return (new JurusanResource($jurusan))->additional([
            'success' => true
        ]);
    }
}