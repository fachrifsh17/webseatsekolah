<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProfilSekolahResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'sejarah' => $this->sejarah,
            'visi' => $this->visi,
            'misi' => $this->misi,
            'npsn' => $this->npsn,
            'akreditasi' => $this->akreditasi,
            'sambutan_kepsek' => $this->sambutan_kepsek,
            // Media/URL
            'foto_kepsek_url' => $this->foto_kepsek  ? asset('storage/' . $this->foto_kepsek)  : null,
            // Metadata
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}