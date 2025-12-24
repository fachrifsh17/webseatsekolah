<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BeritaResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'judul' => $this->judul,
            'isi' => $this->isi_berita,
            'tanggal_publikasi' => $this->tanggal_publikasi,
            'foto_url' => $this->foto ? asset('storage/' . $this->foto) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}