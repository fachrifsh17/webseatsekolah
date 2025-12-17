<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class KurikulumResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'judul' => $this->judul,
            'penjelasan_kurikulum' => $this->penjelasan_kurikulum,
            
            // Media/URL
            'file_jadwal_url' => $this->file_jadwal_path 
                                 ? asset('storage/' . $this->file_jadwal_path) 
                                 : null,
            
            // Metadata
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}