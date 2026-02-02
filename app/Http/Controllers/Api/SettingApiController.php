<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SekolahSetting;
use Symfony\Component\HttpFoundation\Response;

class SettingApiController extends Controller
{
    public function index()
    {
        $data = SekolahSetting::select('tagline', 'logo', 'pesan_selamat_datang')->first(); 
        
        return response()->json([
            'success' => true,
            'data'    => $data
        ], Response::HTTP_OK);
    }
}