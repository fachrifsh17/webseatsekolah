<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon; // Tambahkan ini jika diperlukan

class AlbumResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'nama_album'       => $this->nama_album,
            
            // Mengubah format tanggal_kegiatan menjadi Y-m-d
            'tanggal_kegiatan' => $this->tanggal_kegiatan 
                                  ? Carbon::parse($this->tanggal_kegiatan)->format('Y-m-d') 
                                  : null,
            
            // URL Lengkap untuk Public
            'cover_url'        => $this->cover_path ? asset(Storage::url($this->cover_path)) : null,
            
            'jumlah_media'     => $this->media_count ?? 0,
            
            // Relasi media
            'media'            => MediaResource::collection($this->whenLoaded('media')),
            
            'created_at'       => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'       => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}