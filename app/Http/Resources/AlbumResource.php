<?php

namespace App\Http\Resources;

use App\Http\Resources\MediaResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AlbumResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'nama_album' => $this->nama_album,
            'tanggal_kegiatan' => $this->tanggal_kegiatan ? $this->tanggal_kegiatan->toDateString() : null,
            'cover_url' => $this->cover_path ? Storage::url($this->cover_path) : null,
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }
}
