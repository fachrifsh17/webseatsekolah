<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fasilitas;
use Symfony\Component\HttpFoundation\Response;

class FasilitasApiController extends Controller
{
    public function index()
    {
        $data = Fasilitas::orderBy('id', 'desc')->get();
        return response()->json(['success' => true, 'data' => $data], Response::HTTP_OK);
    }

    public function show(Fasilitas $fasilita)
    {
        return response()->json(['success' => true, 'data' => $fasilita], Response::HTTP_OK);
    }
}