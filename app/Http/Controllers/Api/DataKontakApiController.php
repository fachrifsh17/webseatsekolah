<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DataKontakResource;
use App\Models\DataKontak;
use Symfony\Component\HttpFoundation\Response;

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
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data kontak sekolah berhasil dimuat.',
            'data' => new DataKontakResource($dataKontak),
        ], Response::HTTP_OK);
    }
}
