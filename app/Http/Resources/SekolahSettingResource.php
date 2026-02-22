<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SekolahSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'tagline'              => $this->tagline,
            'logo_url'             => $this->logo ? Storage::url($this->logo) : null,
            'pesan_selamat_datang' => $this->pesan_selamat_datang,

            // Gunakan whenHas untuk mengecek apakah kolom ini ada di hasil query
            // Jika di Controller kamu tidak select 'buku_poin_path', maka baris ini HILANG dari JSON
            'buku_poin_url'        => $this->whenHas('buku_poin_path', function() {
                return $this->buku_poin_path ? Storage::url($this->buku_poin_path) : null;
            }),

            // Sama dengan wa kesiswaan, akan hilang jika tidak di-select di controller
            'no_wa_kesiswaan'      => $this->whenHas('no_wa_kesiswaan'),

            'updated_at'           => $this->updated_at ? $this->updated_at->format('d-m-Y H:i') : null,
        ];
    }
}