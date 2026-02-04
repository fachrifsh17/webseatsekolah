<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TahunAjaran;
use App\Http\Resources\TahunAjaranResource;
use Symfony\Component\HttpFoundation\Response;

class TahunAjaranApiController extends Controller
{
    public function index()
    {
        $tahunAjaran = TahunAjaran::where('is_active', true)
            ->orderBy('nama', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => TahunAjaranResource::collection($tahunAjaran)
        ], Response::HTTP_OK);
    }

    public function show(TahunAjaran $tahunAjaran)
    {
        if (! $tahunAjaran->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Data tahun ajaran aktif tidak ditemukan.'
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data'    => new TahunAjaranResource($tahunAjaran)
        ], Response::HTTP_OK);
    }
}
