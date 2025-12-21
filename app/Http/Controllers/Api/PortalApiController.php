<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PortalSosmed;
use Symfony\Component\HttpFoundation\Response;

class PortalApiController extends Controller
{
    public function index()
    {
        $data = PortalSosmed::orderBy('nama_platform')->get();
        return response()->json([
            'success' => true,
            'data' => $data
        ], Response::HTTP_OK);
    }

    public function show(PortalSosmed $portal)
    {
        return response()->json([
            'success' => true,
            'data' => $portal
        ], Response::HTTP_OK);
    }
}