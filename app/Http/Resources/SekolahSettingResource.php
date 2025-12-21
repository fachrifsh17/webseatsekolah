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
            'id' => $this->id,
            'tagline' => $this->tagline,
            'logo_url' => $this->logo ? url(Storage::url($this->logo)) : null,
            'pesan_selamat_datang' => $this->pesan_selamat_datang,
            'buku_poin_url' => $this->buku_poin_path ? url(Storage::url($this->buku_poin_path)) : null,
            'no_wa_kesiswaan' => $this->no_wa_kesiswaan,
            'updated_at' => $this->updated_at->format('d-m-Y H:i'),
        ];
    }
}