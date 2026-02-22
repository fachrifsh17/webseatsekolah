<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SekolahSetting;
use App\Http\Resources\SekolahSettingResource;
use Symfony\Component\HttpFoundation\Response;

class SettingApiController extends Controller
{
    public function index()
    {
        // Kita batasi kolom yang diambil langsung dari database
        // Tanpa buku_poin dan tanpa no_wa_kesiswaan
        $setting = SekolahSetting::select([
            'id',
            'tagline',
            'logo',
            'pesan_selamat_datang',
            'updated_at'
        ])->first(); 
        
        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Data setting tidak ditemukan'
            ], Response::HTTP_NOT_FOUND);
        }

        // Kembalikan menggunakan Resource
        return (new SekolahSettingResource($setting))->additional([
            'success' => true
        ]);
    }
}