<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jurusan;
use Symfony\Component\HttpFoundation\Response;

class JurusanApiController extends Controller
{
    public function index()
    {
        $data = Jurusan::orderBy('nama_jurusan')->get();
        return response()->json(['success' => true, 'data' => $data], Response::HTTP_OK);
    }

    public function show(Jurusan $jurusan)
    {
        return response()->json(['success' => true, 'data' => $jurusan], Response::HTTP_OK);
    }
}