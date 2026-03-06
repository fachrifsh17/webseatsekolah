<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class AlbumResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'nama_album'       => $this->nama_album,
            
            'tanggal_kegiatan' => $this->tanggal_kegiatan 
                                  ? Carbon::parse($this->tanggal_kegiatan)->format('Y-m-d') 
                                  : null,
            
            // Perubahan di sini: Langsung arahkan ke folder di public
            'cover_url'        => $this->cover_path 
                                  ? asset('uploads/album/' . str_replace('uploads/album/', '', $this->cover_path)) 
                                  : null,
            
            'jumlah_media'     => $this->media_count ?? 0,
            
            'media'            => MediaResource::collection($this->whenLoaded('media')),
            
            'created_at'       => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'       => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}