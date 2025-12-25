<?php

namespace App\Http\Resources;

// Asumsi Anda memiliki AlbumResource
use App\Http\Resources\AlbumResource; 
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray($request)
    {
        $mediaUrl = $this->media_path ? asset('storage/' . $this->media_path) : null;
        
        return [
            'id' => $this->id,
            'album_id' => $this->album_id,
            'media_url' => $mediaUrl,
            'jenis_media' => $this->jenis_media,
            'keterangan' => $this->keterangan,
            
            // Relasi Album
            'album' => new AlbumResource($this->whenLoaded('album')),
            
            // Metadata
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}