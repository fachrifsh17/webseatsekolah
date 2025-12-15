<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'album_id' => $this->album_id,
            'media_path' => $this->media_path,
            'jenis_media' => $this->jenis_media,
            'keterangan' => $this->keterangan,
            'url' => $this->media_path
                ? (filter_var($this->media_path, FILTER_VALIDATE_URL) ? $this->media_path : asset('storage/'.$this->media_path))
                : null,
        ];
    }
}
