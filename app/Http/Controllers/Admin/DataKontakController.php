<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataKontak;
use App\Http\Requests\UpdateDataKontakRequest;
use App\Http\Resources\DataKontakResource;
use Illuminate\Http\JsonResponse;

class DataKontakController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:admin'); // lowercase agar konsisten
        $this->middleware('log.admin')->only(['update']);
    }

    // GET /api/admin/data-kontak
    public function index(): JsonResponse
    {
        $dataKontak = DataKontak::firstOrCreate(
            ['id' => 1],
            [
                'alamat_lengkap' => '-',
                'telepon'        => '-',
                'email_resmi'    => '-',
                'peta_embed_code'=> null,
            ]
        );

        return response()->json([
            'success' => true,
            'data'    => new DataKontakResource($dataKontak),
        ]);
    }

    // PUT /api/admin/data-kontak
    public function update(UpdateDataKontakRequest $request): JsonResponse
    {
        $dataKontak = DataKontak::firstOrCreate(
            ['id' => 1],
            [
                'alamat_lengkap' => '-',
                'telepon'        => '-',
                'email_resmi'    => '-',
                'peta_embed_code'=> null,
            ]
        );

        $dataKontak->update($request->validated());

        return response()->json([
            'success' => true,
            'data'    => new DataKontakResource($dataKontak->fresh()),
        ]);
    }
}
