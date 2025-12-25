<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class JurusanResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'nama_jurusan' => $this->nama_jurusan,
            'deskripsi' => $this->deskripsi,
            
            // Media/URL
            'foto_url' => $this->foto 
                          ? asset('storage/' . $this->foto) 
                          : null,
            
            // Metadata
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}