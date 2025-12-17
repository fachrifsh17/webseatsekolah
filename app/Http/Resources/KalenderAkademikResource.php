<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class KalenderAkademikResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'kegiatan' => $this->kegiatan,
            'tanggal_mulai' => $this->tanggal_mulai,
            'tanggal_selesai' => $this->tanggal_selesai,
            'kategori' => $this->kategori,
            
            // Metadata
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}