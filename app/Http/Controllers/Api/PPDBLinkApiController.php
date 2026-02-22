<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PPDBLink; // Nama model tetap
use App\Http\Resources\PpdbLinkResource; // Panggil Resourcenya
use Symfony\Component\HttpFoundation\Response;

class PpdbLinkApiController extends Controller
{
    public function index()
    {
        // Mengambil semua data link PPDB
        $data = PPDBLink::all();
        
        // Mengembalikan dalam bentuk collection lewat Resource
        return PpdbLinkResource::collection($data)->additional([
            'success' => true,
            'message' => 'Data link PPDB berhasil dimuat.'
        ]);
    }

    public function show(PPDBLink $ppdbLink)
    {
        // Mengembalikan satu data tunggal lewat Resource
        return (new PpdbLinkResource($ppdbLink))->additional([
            'success' => true
        ]);
    }
}