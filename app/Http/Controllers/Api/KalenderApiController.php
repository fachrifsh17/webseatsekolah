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
        // Mengambil agenda yang semesternya sedang aktif
        // Dimana semester tersebut juga memverifikasi tahun ajaran yang aktif
        $data = KalenderAkademik::with(['semester.tahunAjaran'])
            ->whereHas('semester', function ($query) {
                $query->where('is_active', true) // Semester aktif
                      ->whereHas('tahunAjaran', function ($q) {
                          $q->where('is_active', true); // Tahun Ajaran aktif
                      });
            })
            ->orderBy('tanggal_mulai', 'asc')
            ->paginate(10); 

        return KalenderAkademikResource::collection($data)->additional([
            'success' => true, 
            'message' => 'Daftar kalender akademik semester aktif'
        ]);
    }

    public function show($id)
    {
        $kalender = KalenderAkademik::with(['semester.tahunAjaran'])
            ->whereHas('semester', function ($query) {
                $query->where('is_active', true)
                      ->whereHas('tahunAjaran', function ($q) {
                          $q->where('is_active', true);
                      });
            })
            ->find($id);

        if (!$kalender) {
            return response()->json([
                'success' => false,
                'message' => 'Agenda tidak ditemukan pada periode aktif saat ini',
            ], Response::HTTP_NOT_FOUND);
        }

        return (new KalenderAkademikResource($kalender))->additional([
            'success' => true
        ]);
    }
}