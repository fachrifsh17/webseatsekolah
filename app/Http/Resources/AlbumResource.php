<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AlbumResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'nama_album'       => $this->nama_album,
            'tanggal_kegiatan' => $this->tanggal_kegiatan?->format('Y-m-d'),
            'cover_url'        => $this->cover_path ? Storage::url($this->cover_path) : null,
            'media'            => MediaResource::collection($this->whenLoaded('media')),
            'created_at'       => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'       => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
