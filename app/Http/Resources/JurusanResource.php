<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class JurusanResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'           => $this->id,
            'nama_jurusan' => $this->nama_jurusan,
            'deskripsi'    => $this->deskripsi,
            
            // Menyesuaikan path ke folder uploads/jurusan di public
            'foto_url'     => $this->foto 
                              ? asset('uploads/jurusan/' . str_replace('uploads/jurusan/', '', $this->foto)) 
                              : null,
                              
            'is_active'    => $this->is_active,
            'created_at'   => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'   => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}