<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProfilSekolah;
use Symfony\Component\HttpFoundation\Response;

class ProfilApiController extends Controller
{
    public function index()
    {
        // Mengambil data profil pertama (karena profil sekolah biasanya hanya 1 record)
        $data = ProfilSekolah::first();
        
        return response()->json([
            'success' => true,
            'data' => $data
        ], Response::HTTP_OK);
    }

    public function show(ProfilSekolah $profil)
    {
        return response()->json([
            'success' => true,
            'data' => $profil
        ], Response::HTTP_OK);
    }
}