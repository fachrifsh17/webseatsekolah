<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class JabatanResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'nama_jabatan' => $this->nama_jabatan,
            'slug'         => $this->slug,
            'keterangan'   => $this->keterangan,
            'created_at'   => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'   => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}