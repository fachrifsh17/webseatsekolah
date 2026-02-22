<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ekstrakurikuler;
use App\Http\Resources\EkstrakurikulerResource; // 1. IMPORT RESOURCE
use Symfony\Component\HttpFoundation\Response;

class EkstrakurikulerApiController extends Controller
{
    public function index()
    {
        // 2. Tambahkan filter is_active pada relasi pembina
        $ekskul = Ekstrakurikuler::with('pembina')
            ->whereHas('pembina', function ($query) {
                $query->where('is_active', true);
            })
            ->orderBy('nama_ekskul')
            ->get();

        // 3. Gunakan Resource::collection() supaya data difilter
        return EkstrakurikulerResource::collection($ekskul)->additional([
            'success' => true,
            'message' => 'Daftar ekstrakurikuler berhasil dimuat'
        ]);
    }

    public function show($id)
    {
        // Cari data dengan filter pembina aktif
        $ekskul = Ekstrakurikuler::with('pembina')
            ->whereHas('pembina', function ($query) {
                $query->where('is_active', true);
            })
            ->find($id);

        if (!$ekskul) {
            return response()->json([
                'success' => false,
                'message' => 'Ekskul tidak ditemukan atau pembina tidak aktif'
            ], Response::HTTP_NOT_FOUND);
        }

        // 4. Gunakan 'new Resource' untuk data tunggal
        return (new EkstrakurikulerResource($ekskul))->additional([
            'success' => true
        ]);
    }
}