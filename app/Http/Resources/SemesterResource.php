<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SemesterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'tahun_ajaran_id' => $this->tahun_ajaran_id,
            'nama'            => $this->nama,
            'tahun'           => $this->tahun,
            'is_active'       => (bool) $this->is_active,
            
            'tahun_ajaran'    => new TahunAjaranResource($this->whenLoaded('tahunAjaran')),
            
            'created_at'      => $this->created_at?->format('d-m-Y H:i'),
            'updated_at'      => $this->updated_at?->format('d-m-Y H:i'),
        ];
    }
}