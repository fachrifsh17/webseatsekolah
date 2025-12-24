<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PPDBLinkResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'url_link' => $this->url_link,
            'status_ppdb' => $this->status_ppdb,
            
            // Metadata standar
            'updated_at' => $this->updated_at,
        ];
    }
}