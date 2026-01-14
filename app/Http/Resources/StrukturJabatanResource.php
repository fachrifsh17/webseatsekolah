<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StrukturJabatanResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'urutan'        => (int) $this->urutan_tampil,
            
            'jabatan' => [
                'id'   => $this->jabatan_id,
                'nama' => $this->jabatan->nama_jabatan ?? null,
                'slug' => $this->jabatan->slug ?? null,
            ],

            'pejabat' => $this->whenLoaded('guru', function () {
                return [
                    'id'   => $this->guru->id,
                    'nama' => $this->guru->nama,
                    'nip'  => $this->guru->nip,
                    'foto' => $this->guru->foto,
                ];
            }),

            'periode' => $this->periode_mulai?->format('Y-m-d'),
        ];
    }
}