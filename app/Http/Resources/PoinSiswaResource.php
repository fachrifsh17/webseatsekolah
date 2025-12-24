<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PoinSiswaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'siswa'        => [
                'id'   => $this->siswa_id,
                'nama' => $this->siswa->nama_lengkap ?? null,
                'nis'  => $this->siswa->nis ?? null,
            ],
            'guru_pelapor' => [
                'id'   => $this->guru_staf_id,
                'nama' => $this->guruStaf->nama_lengkap ?? null,
            ],
            'tahun_ajaran' => $this->tahunAjaran->nama ?? null,
            'indikator'    => $this->indikator,
            'poin_positif' => $this->poin_positif ?? 0,
            'poin_negatif' => $this->poin_negatif ?? 0,
            'total_poin'   => ($this->poin_positif ?? 0) - ($this->poin_negatif ?? 0),
            'tanggal'      => $this->tanggal,
            'created_at'   => $this->created_at ? $this->created_at->format('d-m-Y H:i') : null,
            'updated_at'   => $this->updated_at ? $this->updated_at->format('d-m-Y H:i') : null,
        ];
    }
}