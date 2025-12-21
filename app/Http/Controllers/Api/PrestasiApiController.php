<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prestasi;
use Symfony\Component\HttpFoundation\Response;

class PrestasiApiController extends Controller
{
    public function index()
    {
        $data = Prestasi::orderBy('tahun', 'desc')->get();
        return response()->json(['success' => true, 'data' => $data], Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function show(Prestasi $prestasi)
    {
        return response()->json(['success' => true, 'data' => $prestasi], Response::HTTP_OK);
    }
}