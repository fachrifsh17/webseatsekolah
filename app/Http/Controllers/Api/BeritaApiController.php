<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Berita;
use Symfony\Component\HttpFoundation\Response;

class BeritaApiController extends Controller
{
    public function index()
    {
        $berita = Berita::orderBy('tanggal_publikasi', 'desc')->paginate(10);
        return response()->json(['success' => true, 'data' => $berita], Response::HTTP_OK);
    }

    public function show(Berita $berita)
    {
        return response()->json(['success' => true, 'data' => $berita], Response::HTTP_OK);
    }
}