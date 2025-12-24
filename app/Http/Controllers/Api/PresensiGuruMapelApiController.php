<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PresensiGuruMapel;
use App\Http\Resources\PresensiGuruMapelResource;
use Symfony\Component\HttpFoundation\Response;

class PresensiGuruMapelApiController extends Controller
{
    public function index()
    {
        $presensi = PresensiGuruMapel::with(['rincianSiswa.siswa'])
            ->orderBy('id', 'desc')
            ->get();
            
        return response()->json([
            'success' => true, 
            'data' => PresensiGuruMapelResource::collection($presensi)
        ], Response::HTTP_OK);
    }

    public function show($id)
    {
        $presensi = PresensiGuruMapel::with(['rincianSiswa.siswa'])->findOrFail($id);
        
        return response()->json([
            'success' => true, 
            'data' => new PresensiGuruMapelResource($presensi)
        ], Response::HTTP_OK);
    }
}