<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pengumuman;
use Symfony\Component\HttpFoundation\Response;

class PengumumanApiController extends Controller
{
    public function index()
    {
        $data = Pengumuman::orderBy('tanggal_publikasi', 'desc')->get();
        return response()->json(['success' => true, 'data' => $data], Response::HTTP_OK);
    }

    public function show(Pengumuman $pengumuman)
    {
        return response()->json(['success' => true, 'data' => $pengumuman], Response::HTTP_OK);
    }
}