<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'judul'        => $this->judul,
            'url_link'     => $this->url_link,
            'aktif_sampai' => $this->aktif_sampai ? \Carbon\Carbon::parse($this->aktif_sampai)->format('d-m-Y H:i') : null,
            
            // Gunakan asset() untuk membungkus Storage::url agar menjadi URL lengkap
            'foto_url'     => $this->foto ? asset(Storage::url($this->foto)) : null,
            
            'created_at'   => $this->created_at?->format('d-m-Y H:i'),
        ];
    }
}