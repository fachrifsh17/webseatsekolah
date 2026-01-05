<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StrukturJabatanResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                     => $this->id,
            'nama_jabatan_struktural'=> $this->nama_jabatan_struktural,
            'periode_mulai'          => $this->periode_mulai?->format('Y-m-d'),
            'urutan_tampil'          => (int) $this->urutan_tampil,
            
            'pejabat' => $this->whenLoaded('pejabat', function () {
                return new GuruResource($this->pejabat); 
            }),
            
            'created_at'             => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'             => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
