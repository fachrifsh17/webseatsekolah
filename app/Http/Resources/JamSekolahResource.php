<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JamSekolahResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'tahun_ajaran_id' => $this->tahun_ajaran_id,
            'tahun_ajaran'    => $this->whenLoaded('tahunAjaran', function () {
                return [
                    'id'       => $this->tahunAjaran?->id,
                    'nama'     => $this->tahunAjaran?->nama,
                    'semester' => $this->tahunAjaran?->semester,
                    'aktif'    => (bool) ($this->tahunAjaran?->is_active ?? false),
                ];
            }),
            'hari'            => $this->hari,
            'jam_ke'          => $this->jam_ke,
            'waktu_mulai'     => $this->waktu_mulai ? $this->waktu_mulai->format('H:i') : null,
            'waktu_selesai'   => $this->waktu_selesai ? $this->waktu_selesai->format('H:i') : null,
            'jenis'           => $this->jenis,
            'created_at'      => $this->created_at?->format('d-m-Y H:i'),
            'updated_at'      => $this->updated_at?->format('d-m-Y H:i'),
        ];
    }
}
