<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TahunAjaran;
use App\Http\Resources\TahunAjaranResource;

class TahunAjaranApiController extends Controller
{
    public function index()
    {
        $tahunAjaran = TahunAjaran::orderBy('nama', 'desc')->get();
        return TahunAjaranResource::collection($tahunAjaran);
    }

    public function show($id)
    {
        $tahunAjaran = TahunAjaran::find($id);

        if (!$tahunAjaran) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        return new TahunAjaranResource($tahunAjaran);
    }
}
