<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PpdbLinkResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'          => $this->id,
            'url_link'    => $this->url_link,
            'status_ppdb' => $this->status_ppdb,
            'updated_at'  => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
