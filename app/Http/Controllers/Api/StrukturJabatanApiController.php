<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StrukturJabatan;
use Symfony\Component\HttpFoundation\Response;

class StrukturJabatanApiController extends Controller
{
    public function index()
    {
        $data = StrukturJabatan::with(['guruStaf', 'jabatan'])
            ->orderBy('urutan_tampil')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $data
        ], Response::HTTP_OK);
    }

    public function show(StrukturJabatan $strukturJabatan)
    {
        $strukturJabatan->load(['guruStaf', 'jabatan']);

        return response()->json([
            'success' => true,
            'data'    => $strukturJabatan
        ], Response::HTTP_OK);
    }
}
