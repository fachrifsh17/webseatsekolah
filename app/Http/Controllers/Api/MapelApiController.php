<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MataPelajaran;
use Symfony\Component\HttpFoundation\Response;

class MapelApiController extends Controller
{
    public function index()
    {
        $mapel = MataPelajaran::with('jurusan')->orderBy('nama_mapel')->get();
        return response()->json(['success' => true, 'data' => $mapel], Response::HTTP_OK);
    }

    public function show(MataPelajaran $mapel)
    {
        $mapel->load('jurusan');
        return response()->json(['success' => true, 'data' => $mapel], Response::HTTP_OK);
    }
}