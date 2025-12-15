<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PengumumanResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'judul' => $this->judul,
            'isi' => $this->isi_pengumuman,
            'tanggal_publikasi' => $this->tanggal_publikasi,
            'penting' => (bool) $this->penting,
        ];
    }
}
