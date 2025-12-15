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
            'foto_url' => $this->foto ? asset('storage/'.$this->foto) : null,
        ];
    }
}
