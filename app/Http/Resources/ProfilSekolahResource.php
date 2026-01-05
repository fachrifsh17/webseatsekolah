<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfilSekolahResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'nama_sekolah'    => $this->nama_sekolah,
            'npsn'            => $this->npsn,
            'akreditasi'      => $this->akreditasi,
            'visi'            => $this->visi,
            'misi'            => $this->misi,
            'sejarah'         => $this->sejarah,
            'sambutan_kepsek' => $this->sambutan_kepsek,
            'kepala_sekolah'  => $this->guruStaf ? [
                'id'   => $this->guruStaf->id,
                'nama' => $this->guruStaf->nama,
                'nip'  => $this->guruStaf->nip,
                'foto' => $this->guruStaf->foto,
            ] : null,
            'updated_at'      => $this->updated_at?->format('d-m-Y H:i'),
        ];
    }
}
