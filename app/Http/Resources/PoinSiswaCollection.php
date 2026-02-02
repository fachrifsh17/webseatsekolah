<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PoinSiswaCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Daftar Poin Siswa Berhasil Diambil',
            'data'    => PoinSiswaResource::collection($this->collection),
        ];
    }
}
