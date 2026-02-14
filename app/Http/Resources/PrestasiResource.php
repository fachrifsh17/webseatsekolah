<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PrestasiResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'judul' => $this->judul,
            'tahun' => $this->tahun,
            'tingkat' => $this->tingkat,
            'kategori' => $this->kategori,
            'foto_url' => $this->foto 
                        ? asset('storage/' . $this->foto) 
                        : null,
            
            // Format: 2026-02-14 (Tanpa jam dan detik)
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d') : null,
        ];
    }
}