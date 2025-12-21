<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PPDBLink;
use Symfony\Component\HttpFoundation\Response;

class PpdbLinkApiController extends Controller
{
    public function index()
    {
        $data = PPDBLink::all();
        return response()->json([
            'success' => true,
            'data' => $data
        ], Response::HTTP_OK);
    }

    public function show(PPDBLink $ppdbLink)
    {
        return response()->json([
            'success' => true,
            'data' => $ppdbLink
        ], Response::HTTP_OK);
    }
}