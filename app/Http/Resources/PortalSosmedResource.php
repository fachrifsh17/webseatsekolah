<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PortalSosmedResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'            => $this->id,
            'nama_platform' => $this->nama_platform,
            'url_link'      => $this->url_link,
            'tipe'          => $this->tipe,
            'created_at'    => $this->created_at ? $this->created_at->format('d-m-Y H:i') : null,
            'updated_at'    => $this->updated_at ? $this->updated_at->format('d-m-Y H:i') : null,
        ];
    }
}