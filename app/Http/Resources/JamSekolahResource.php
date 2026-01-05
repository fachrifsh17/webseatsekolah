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
            'judul'           => $this->judul,
            'tahun_ajaran_id' => $this->tahun_ajaran_id,
            'tahun_ajaran'    => $this->whenLoaded('tahunAjaran', function () {
                return [
                    'id'       => $this->tahunAjaran?->id,
                    'nama'     => $this->tahunAjaran?->nama,
                    'semester' => $this->tahunAjaran?->semester,
                    'aktif'    => (bool) ($this->tahunAjaran?->aktif ?? false),
                ];
            }),
            'file_path'       => $this->file_path,
            'created_at'      => $this->created_at?->format('d-m-Y H:i'),
            'updated_at'      => $this->updated_at?->format('d-m-Y H:i'),
        ];
    }
}
