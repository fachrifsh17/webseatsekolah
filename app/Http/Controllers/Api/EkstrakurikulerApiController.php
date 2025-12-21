<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ekstrakurikuler;
use Symfony\Component\HttpFoundation\Response;

class EkstrakurikulerApiController extends Controller
{
    public function index()
    {
        $ekskul = Ekstrakurikuler::with('pembina')->orderBy('nama_ekskul')->get();
        return response()->json([
            'success' => true,
            'data' => $ekskul
        ], Response::HTTP_OK);
    }

    public function show(Ekstrakurikuler $ekskul)
    {
        $ekskul->load('pembina');
        return response()->json([
            'success' => true,
            'data' => $ekskul
        ], Response::HTTP_OK);
    }
}