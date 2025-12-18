<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataKontak;
use App\Http\Requests\UpdateDataKontakRequest;
use App\Http\Resources\DataKontakResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DataKontakController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth.token'),
            new Middleware('role:Admin,SuperAdmin'),
            new Middleware('log.admin', only: ['update']),
        ];
    }

    public function show(): DataKontakResource
    {
        $dataKontak = DataKontak::firstOrCreate(
            ['id' => 1],
            [
                'alamat_lengkap' => '-',
                'telepon' => '-',
                'email_resmi' => '-',
                'peta_embed_code' => null
            ]
        );

        return new DataKontakResource($dataKontak);
    }

    public function update(UpdateDataKontakRequest $request): DataKontakResource
    {
        $dataKontak = DataKontak::firstOrNew(['id' => 1]);
        
        $dataKontak->fill($request->validated());
        $dataKontak->save();
        
        return new DataKontakResource($dataKontak);
    }
}