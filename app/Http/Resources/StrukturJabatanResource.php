<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StrukturJabatanResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'nama_jabatan_struktural' => $this->nama_jabatan_struktural,
            'periode_mulai' => $this->periode_mulai,
            'periode_selesai' => $this->periode_selesai ?? null, // Asumsi ada periode_selesai
            'urutan_tampil' => (int) $this->urutan_tampil,
            
            'pejabat' => $this->whenLoaded('pejabat', function () {
                return new GuruResource($this->pejabat); 
            }),
            
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}