<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class KalenderAkademikResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'kegiatan'        => $this->kegiatan,
            'tanggal_mulai'   => $this->tanggal_mulai?->format('Y-m-d'),
            'tanggal_selesai' => $this->tanggal_selesai?->format('Y-m-d'),
            'kategori'        => $this->kategori,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
