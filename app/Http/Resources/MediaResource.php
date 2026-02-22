<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MediaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'album_id'    => $this->album_id,
            
            // Menggunakan asset() untuk mengubah path menjadi URL lengkap (http://...)
            'media_url'   => $this->media_path ? asset(Storage::url($this->media_path)) : null,
            
            'jenis_media' => $this->jenis_media,
            'keterangan'  => $this->keterangan,

            // Relasi Album (Pastikan AlbumResource juga menggunakan asset() untuk cover_url)
            'album'       => $this->whenLoaded('album', function () {
                return new AlbumResource($this->album);
            }),

            // Metadata
            'created_at'  => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'  => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}