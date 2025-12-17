<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DataKontakResource;
use App\Models\DataKontak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DataKontakApiController extends Controller
{
    
    public function update(Request $request)
    {
        // 1. Validasi Input HANYA untuk kolom yang ada di DB
        $validator = Validator::make($request->all(), [
            'alamat_lengkap' => 'nullable|string|max:255', 
            'telepon' => 'nullable|string|max:30',
            'email_resmi' => 'nullable|email|max:100', 
            'peta_embed_code' => 'nullable|string', 
            
            // FIELD SOSIAL MEDIA DIHAPUS DARI VALIDASI
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        
        // 2. Ambil atau Buat Record
        $dataKontak = DataKontak::updateOrCreate(
            ['id' => 1],
            $validator->validated()
        );

        // 3. Berikan Respons Sukses
        return response()->json([
            'message' => 'Data kontak sekolah berhasil diperbarui.',
            'data' => new DataKontakResource($dataKontak),
        ]);
    }

    public function show()
    {
        $dataKontak = DataKontak::first();

        if (!$dataKontak) {
            return response()->json([
                'message' => 'Data kontak sekolah belum diinisiasi.',
                'data' => (object) [],
            ], 404);
        }

        return new DataKontakResource($dataKontak);
    }
}