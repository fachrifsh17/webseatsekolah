<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SekolahSettingResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'tagline' => $this->tagline,
            // Media/URL
            'logo_url' => $this->logo  ? asset('storage/' . $this->logo)  : null,   
            'pesan_selamat_datang' => $this->pesan_selamat_datang,
            // Metadata
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}