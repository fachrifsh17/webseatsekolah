<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PesanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama_lengkap' => $this->nama_lengkap,
            'email' => $this->email,
            'subjek' => $this->subjek,
            'pesan' => $this->pesan,
            'status' => $this->status,
            'tanggal_masuk' => $this->created_at->format('d-m-Y H:i'),
        ];
    }
}