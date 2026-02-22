<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KalenderAkademik;
use App\Http\Resources\KalenderAkademikResource;
use Symfony\Component\HttpFoundation\Response;

class KalenderApiController extends Controller
{
    public function index()
    {
        // 1. Ganti get() menjadi paginate()
        // Kita gunakan 10 data per halaman (bisa diganti sesuai kebutuhan)
        $data = KalenderAkademik::with('tahunAjaran')
            ->whereHas('tahunAjaran', function ($query) {
                $query->where('is_active', true);
            })
            ->orderBy('tanggal_mulai', 'asc')
            ->paginate(10); 

        // 2. Resource::collection otomatis menangani metadata pagination
        return KalenderAkademikResource::collection($data)->additional([
            'success' => true, 
            'message' => 'Daftar kalender akademik tahun ajaran aktif'
        ]);
    }

    public function show($id)
    {
        $kalender = KalenderAkademik::with('tahunAjaran')
            ->whereHas('tahunAjaran', function ($query) {
                $query->where('is_active', true);
            })
            ->find($id);

        if (!$kalender) {
            return response()->json([
                'success' => false,
                'message' => 'Agenda tidak ditemukan atau tahun ajaran tidak aktif',
            ], Response::HTTP_NOT_FOUND);
        }

        return (new KalenderAkademikResource($kalender))->additional([
            'success' => true
        ]);
    }
}