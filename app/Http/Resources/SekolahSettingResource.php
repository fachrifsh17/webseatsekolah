<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SekolahSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'tagline'              => $this->tagline,
            
            'pesan_selamat_datang' => $this->pesan_selamat_datang,

            'buku_poin_url'        => $this->whenHas('buku_poin_path', function() {
                return $this->buku_poin_path 
                    ? asset('uploads/setting/' . str_replace('uploads/setting/', '', $this->buku_poin_path)) 
                    : null;
            }),

            'no_wa_kesiswaan'      => $this->whenHas('no_wa_kesiswaan'),

            'updated_at'           => $this->updated_at ? $this->updated_at->format('d-m-Y H:i') : null,
        ];
    }
}