<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StrukturJabatan;
use App\Http\Resources\StrukturJabatanResource; // <-- PASTIKAN INI DIIMPORT
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;

class StrukturJabatanApiController extends Controller
{
    public function index(Request $request)
    {
        $data = StrukturJabatan::with(['guruStaf', 'jabatan'])
            ->orderBy('urutan_tampil')
            ->get();

        // JANGAN kembalikan $data langsung. Gunakan Resource::collection()
        return StrukturJabatanResource::collection($data)->additional([
            'success' => true,
            'message' => 'Data struktur organisasi berhasil dimuat.'
        ]);
    }
}