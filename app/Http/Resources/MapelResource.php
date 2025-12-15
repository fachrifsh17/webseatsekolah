<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MapelResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'nama_mapel' => $this->nama_mapel,
            'tipe_mapel' => $this->tipe_mapel,
            'kategori_mapel' => $this->kategori_mapel,
            'jurusan' => new JurusanResource($this->whenLoaded('jurusan')),
        ];
    }
}
