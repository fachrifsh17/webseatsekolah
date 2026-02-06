<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class KurikulumResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'judul'                => $this->judul,
            'penjelasan_kurikulum' => $this->penjelasan_kurikulum,
            'is_active'            => (bool) $this->is_active,
            'file_jadwal_url'      => $this->file_jadwal_path 
                                      ? Storage::url($this->file_jadwal_path) 
                                      : null,
            'created_at'           => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'           => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}