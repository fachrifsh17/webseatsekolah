<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TahunAjaranCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Daftar Tahun Ajaran Berhasil Diambil',
            'data'    => $this->collection,
        ];
    }
}