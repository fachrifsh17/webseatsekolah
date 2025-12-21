<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfilSekolahResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'npsn' => $this->npsn,
            'akreditasi' => $this->akreditasi,
            'visi' => $this->visi,
            'misi' => $this->misi,
            'sejarah' => $this->sejarah,
            'sambutan_kepsek' => $this->sambutan_kepsek,
            'kepala_sekolah' => [
                'id' => $this->guru_staf_id,
                'nama' => $this->kepalaSekolah->nama_lengkap ?? null,
                'nip' => $this->kepalaSekolah->nip ?? null,
                'foto' => $this->kepalaSekolah->foto ?? null,
            ],
            'updated_at' => $this->updated_at->format('d-m-Y H:i'),
        ];
    }
}