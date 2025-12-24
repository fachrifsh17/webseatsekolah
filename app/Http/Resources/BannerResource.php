<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BannerResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'           => $this->id,
            'judul'        => $this->judul,
            'url_link'     => $this->url_link,
            'aktif_sampai' => $this->aktif_sampai,
            'foto_url'     => $this->foto ? asset('storage/'.$this->foto) : null,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}