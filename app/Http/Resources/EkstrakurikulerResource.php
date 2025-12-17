<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EkstrakurikulerResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'nama_ekskul' => $this->nama_ekskul,
            'deskripsi' => $this->deskripsi,
            'hari' => $this->hari,
            'jam_mulai' => $this->jam_mulai ? $this->jam_mulai->format('H:i:s') : null,
            'jam_selesai' => $this->jam_selesai ? $this->jam_selesai->format('H:i:s') : null,
            'pembina_id' => $this->pembina_id,
            'foto' => $this->foto,
            'keterangan' => $this->keterangan,
            'pembina' => $this->whenLoaded('pembina', function () {
                return new GuruResource($this->pembina);
            }),
        ];
    }
}