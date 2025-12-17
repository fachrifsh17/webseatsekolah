<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataKontak;
use App\Http\Requests\UpdateDataKontakRequest;
use App\Http\Resources\DataKontakResource;
use Illuminate\Http\Request;

class DataKontakController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:Admin');
    }

    public function show()
    {
        $dataKontak = DataKontak::firstOrNew(['id' => 1]);

        return new DataKontakResource($dataKontak);
    }

    public function update(UpdateDataKontakRequest $request)
    {
        $dataKontak = DataKontak::firstOrNew(['id' => 1]);

        $dataKontak->alamat_lengkap = $request->alamat_lengkap;
        $dataKontak->telepon = $request->telepon;
        $dataKontak->email_resmi = $request->email_resmi;
        $dataKontak->peta_embed_code = $request->peta_embed_code;

        $dataKontak->save();
        
        return new DataKontakResource($dataKontak);
    }
}