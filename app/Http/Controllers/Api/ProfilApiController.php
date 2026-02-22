<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProfilSekolah;
use App\Http\Resources\ProfilSekolahResource;
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
        
        // Menggunakan Resource untuk satu data (Single Object)
        return (new ProfilSekolahResource($data))->additional([
            'success' => true
        ]);
    }

    // Fungsi show biasanya jarang dipakai jika data profil cuma satu, 
    // tapi jika tetap ingin ada, gunakan Resource juga.
    public function show($id)
    {
        $profil = ProfilSekolah::findOrFail($id);

        return (new ProfilSekolahResource($profil))->additional([
            'success' => true
        ]);
    }
}