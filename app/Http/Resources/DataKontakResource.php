<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DataKontakResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'alamat'           => $this->alamat_lengkap,
            'telepon'          => $this->telepon,       
            'email'            => $this->email_resmi,   
            'maps_embed_code'  => $this->peta_embed_code,
            'updated_at'       => $this->updated_at ? $this->updated_at->format('d-m-Y H:i') : null,
        ];
    }
}
