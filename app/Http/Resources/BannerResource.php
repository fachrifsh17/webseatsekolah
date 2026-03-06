<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'judul'        => $this->judul,
            'url_link'     => $this->url_link,
            'aktif_sampai' => $this->aktif_sampai ? \Carbon\Carbon::parse($this->aktif_sampai)->format('d-m-Y H:i') : null,
            
            // Perubahan: Langsung menunjuk ke folder uploads/banner di public
            'foto_url'     => $this->foto 
                              ? asset('uploads/banner/' . str_replace('uploads/banner/', '', $this->foto)) 
                              : null,
            
            'created_at'   => $this->created_at?->format('d-m-Y H:i'),
        ];
    }
}