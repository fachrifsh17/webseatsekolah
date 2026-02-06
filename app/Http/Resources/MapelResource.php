<?php

namespace App\Http\Resources;

use App\Http\Resources\JurusanResource;
use Illuminate\Http\Resources\Json\JsonResource;

class MapelResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'nama_mapel' => $this->nama_mapel,
            'jurusan_id' => $this->jurusan_id,
            'tipe_mapel' => $this->tipe_mapel,
            'kategori_mapel' => $this->kategori_mapel,
            'is_active' => (bool) $this->is_active, 
            'jurusan' => new JurusanResource($this->whenLoaded('jurusan')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}