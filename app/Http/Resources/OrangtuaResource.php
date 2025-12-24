<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrangtuaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'user'      => [
                'id'       => $this->user_id,
                'username' => $this->user->name ?? null,
            ],
            'nama_ayah' => $this->nama_ayah,
            'nama_ibu'  => $this->nama_ibu,
            'no_hp'     => $this->no_hp,
            'alamat'    => $this->alamat,
            'siswa'     => SiswaResource::collection($this->whenLoaded('siswa')),
            'created_at'=> $this->created_at ? $this->created_at->format('d-m-Y H:i') : null,
            'updated_at'=> $this->updated_at ? $this->updated_at->format('d-m-Y H:i') : null,
        ];
    }
}