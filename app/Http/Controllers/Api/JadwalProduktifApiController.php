<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JadwalProduktif;
use App\Http\Resources\JadwalProduktifResource;
use Illuminate\Http\Request;

class JadwalProduktifApiController extends Controller
{
    public function index()
    {
        $jadwal = JadwalProduktif::with(['jurusan', 'guruStaf'])->latest()->get();
        
        return JadwalProduktifResource::collection($jadwal);
    }

    public function show(JadwalProduktif $jadwalProduktif)
    {
        return new JadwalProduktifResource($jadwalProduktif->load(['jurusan', 'guruStaf']));
    }
}