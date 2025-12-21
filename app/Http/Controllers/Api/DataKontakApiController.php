<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DataKontakResource;
use App\Models\DataKontak;

class DataKontakApiController extends Controller
{
    public function show()
    {
        $dataKontak = DataKontak::first();

        if (!$dataKontak) {
            return response()->json([
                'success' => false,
                'message' => 'Data kontak sekolah belum diinisiasi.',
                'data' => (object) [],
            ], 404);
        }

        return new DataKontakResource($dataKontak);
    }
}