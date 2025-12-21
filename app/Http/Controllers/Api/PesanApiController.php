<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pesan;
use App\Http\Requests\StorePesanRequest;
use App\Http\Resources\PesanResource;
use Illuminate\Http\JsonResponse;

class PesanApiController extends Controller
{
    public function store(StorePesanRequest $request): JsonResponse
    {
        $data = $request->validated();
        
        $pesan = Pesan::create($data);
        
        return response()->json([
            'success' => true,
            'message' => 'Terima kasih, pesan Anda telah kami terima.',
            'data'    => new PesanResource($pesan)
        ], 201);
    }
}