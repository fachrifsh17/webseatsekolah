<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GuruStaf;
use Symfony\Component\HttpFoundation\Response;

class GuruApiController extends Controller
{
    public function index()
    {
        $guru = GuruStaf::with(['jurusan', 'user'])->orderBy('nama')->get();
        return response()->json(['success' => true, 'data' => $guru], Response::HTTP_OK);
    }

    public function show(GuruStaf $guru)
    {
        $guru->load(['jurusan', 'user']);
        return response()->json(['success' => true, 'data' => $guru], Response::HTTP_OK);
    }
}