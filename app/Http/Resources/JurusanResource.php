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
            'foto_url'     => $this->foto ? asset('storage/' . $this->foto) : null,
            'created_at'   => optional($this->created_at)->toDateTimeString(),
            'updated_at'   => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
