<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DataKontakResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Nama API Output | Mengambil dari Kolom DB
            'alamat' => $this->alamat_lengkap, 
            'telepon' => $this->telepon,
            'email' => $this->email_resmi, 
            'maps_embed_code' => $this->peta_embed_code, 
            // Metadata
            'updated_at' => $this->updated_at,
        ];
    }
}