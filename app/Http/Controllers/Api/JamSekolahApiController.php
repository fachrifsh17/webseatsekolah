<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JamSekolah;
use App\Http\Resources\JamSekolahResource;
use Illuminate\Http\Request;

class JamSekolahApiController extends Controller
{
    public function index()
    {
        $jamSekolah = JamSekolah::with('tahunAjaran')->latest()->get();
        
        return JamSekolahResource::collection($jamSekolah);
    }

    public function show(JamSekolah $jamSekolah)
    {
        return new JamSekolahResource($jamSekolah->load('tahunAjaran'));
    }
}