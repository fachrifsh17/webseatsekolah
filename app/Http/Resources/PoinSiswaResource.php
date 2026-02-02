<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
<<<<<<< HEAD
=======
use Illuminate\Support\Carbon;
>>>>>>> master

class PoinSiswaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'siswa'        => [
                'id'   => $this->siswa_id,
<<<<<<< HEAD
                'nama' => $this->siswa->nama ?? $this->siswa->nama_lengkap ?? null,
                'nis'  => $this->siswa->nis ?? null,
            ],
            'guru_pelapor' => [
                'id'   => $this->guru_staf_id,
                'nama' => $this->guruStaf->nama ?? null,
            ],
            'tahun_ajaran' => $this->tahunAjaran->tahun_ajaran ?? $this->tahunAjaran->nama ?? null,
=======
                'nama' => $this->siswa?->nama_lengkap,
                'nis'  => $this->siswa?->nis,
            ],
            'guru_pelapor' => [
                'id'   => $this->guru_staf_id,
                'nama' => $this->guruStaf?->nama,
            ],
            'tahun_ajaran' => $this->tahunAjaran?->nama,
>>>>>>> master
            'indikator'    => $this->indikator,
            'poin_positif' => (int) ($this->poin_positif ?? 0),
            'poin_negatif' => (int) ($this->poin_negatif ?? 0),
            'total_poin'   => (int) (($this->poin_positif ?? 0) - ($this->poin_negatif ?? 0)),
<<<<<<< HEAD
            'tanggal'      => $this->tanggal,
            'created_at'   => $this->created_at ? $this->created_at->format('d-m-Y H:i') : null,
            'updated_at'   => $this->updated_at ? $this->updated_at->format('d-m-Y H:i') : null,
        ];
    }
}
=======

            'tanggal'      => $this->tanggal instanceof Carbon
                ? $this->tanggal->format('d-m-Y')
                : $this->tanggal,

            'created_at'   => $this->created_at instanceof Carbon
                ? $this->created_at->format('d-m-Y H:i')
                : $this->created_at,

            'updated_at'   => $this->updated_at instanceof Carbon
                ? $this->updated_at->format('d-m-Y H:i')
                : $this->updated_at,
        ];
    }
}
>>>>>>> master
