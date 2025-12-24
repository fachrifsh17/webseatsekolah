<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FasilitasResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'             => $this->id,
            'nama_fasilitas' => $this->nama_fasilitas,
            'foto_url'       => $this->foto ? asset('storage/' . $this->foto) : null,
            'keterangan'     => $this->keterangan,
            'created_at'     => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at'     => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }
}