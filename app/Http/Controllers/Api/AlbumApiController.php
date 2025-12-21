<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Album;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AlbumApiController extends Controller
{
    // Mengambil semua album untuk ditampilkan di website publik
    public function index()
    {
        // Disarankan menggunakan pagination agar tidak berat saat data banyak
        $albums = Album::orderBy('tanggal_kegiatan', 'desc')->paginate(12);
        return response()->json(['success' => true, 'data' => $albums], Response::HTTP_OK);
    }

    // Menampilkan detail satu album beserta foto/media di dalamnya
    public function show(Album $album)
    {
        $album->load('media');
        return response()->json(['success' => true, 'data' => $album], Response::HTTP_OK);
    }
    
    // Fungsi store, update, destroy dihapus karena sudah ada di folder Admin
}