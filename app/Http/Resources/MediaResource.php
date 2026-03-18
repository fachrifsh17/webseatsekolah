<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'album_id'    => $this->album_id,
            
            // Mengarahkan langsung ke folder uploads/album/media di public
            'media_url'   => $this->media_path 
                             ? asset('uploads/media/' . str_replace('uploads/album/media/', '', $this->media_path)) 
                             : null,
            
            'jenis_media' => $this->jenis_media,
            'keterangan'  => $this->keterangan,

            'album'       => $this->whenLoaded('album', function () {
                return new AlbumResource($this->album);
            }),

            'created_at'  => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'  => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}