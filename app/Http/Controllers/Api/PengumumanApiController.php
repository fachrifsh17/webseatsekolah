<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pengumuman;
use App\Http\Resources\PengumumanResource;
use Symfony\Component\HttpFoundation\Response;

class PengumumanApiController extends Controller
{
    public function index()
    {
        // Gunakan paginate supaya seragam dengan Berita
        $data = Pengumuman::orderBy('tanggal_publikasi', 'desc')->paginate(10);
        
        return PengumumanResource::collection($data)->additional([
            'success' => true
        ]);
    }

    public function show($id)
    {
        $pengumuman = Pengumuman::findOrFail($id);
        
        return (new PengumumanResource($pengumuman))->additional([
            'success' => true
        ]);
    }
}