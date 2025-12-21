<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KalenderAkademik;
use Symfony\Component\HttpFoundation\Response;

class KalenderApiController extends Controller
{
    public function index()
    {
        $data = KalenderAkademik::orderBy('tanggal_mulai')->get();
        return response()->json([
            'success' => true, 
            'data' => $data
        ], Response::HTTP_OK);
    }

    public function show(KalenderAkademik $kalender)
    {
        return response()->json([
            'success' => true, 
            'data' => $kalender
        ], Response::HTTP_OK);
    }
}