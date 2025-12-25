<?php

namespace App\Http\Resources;

use App\Http\Resources\MediaResource;
use Illuminate\Http\Resources\Json\JsonResource;

class AlbumResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'nama_album' => $this->nama_album,
            'tanggal_kegiatan' => $this->tanggal_kegiatan,
            'cover_url' => $this->cover_path ? asset('storage/' . $this->cover_path) : null,
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}