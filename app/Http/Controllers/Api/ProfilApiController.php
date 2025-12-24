<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProfilSekolah;
use Symfony\Component\HttpFoundation\Response;

class ProfilApiController extends Controller
{
    public function index()
    {
        $data = ProfilSekolah::first();
        
        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Data profil sekolah belum diatur.'
            ], Response::HTTP_NOT_FOUND);
        }
        
        return response()->json([
            'success' => true,
            'data' => $data
        ], Response::HTTP_OK);
    }

    public function show($id)
    {
        $profil = ProfilSekolah::find($id);

        if (!$profil) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan.'
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data' => $profil
        ], Response::HTTP_OK);
    }
}