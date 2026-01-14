<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class EkstrakurikulerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'nama_ekskul'  => $this->nama_ekskul,
            'deskripsi'    => $this->deskripsi,
            'hari'         => $this->hari,
            'jam_mulai'    => $this->jam_mulai ? (string) $this->jam_mulai : null,
            'jam_selesai'  => $this->jam_selesai ? (string) $this->jam_selesai : null,
            'pembina_id'   => $this->pembina_id,
            'foto_url'     => $this->foto ? Storage::url($this->foto) : null,
            'keterangan'   => $this->keterangan,
            'pembina'      => $this->whenLoaded('pembina', function () {
                return [
                    'id'   => $this->pembina->id,
                    'nama' => $this->pembina->nama,
                ];
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}