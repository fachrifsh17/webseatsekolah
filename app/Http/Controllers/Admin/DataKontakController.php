<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataKontak;
use App\Http\Requests\UpdateDataKontakRequest;
use App\Http\Resources\DataKontakResource;

class DataKontakController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['update']);
    }

    public function show(): DataKontakResource
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

        return new DataKontakResource($dataKontak);
    }

    public function update(UpdateDataKontakRequest $request): DataKontakResource
    {
        $dataKontak = DataKontak::firstOrCreate(['id' => 1]);

        $dataKontak->update($request->validated());

        return new DataKontakResource($dataKontak);
    }
}