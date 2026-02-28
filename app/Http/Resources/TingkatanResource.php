<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TingkatanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id, // String (X, XI, XII)
            'nama_tingkatan' => $this->nama_tingkatan,
            // Anda bisa menambahkan data lain jika perlu, misal:
            // 'jumlah_kelas' => $this->kelas()->count(),
        ];
    }
}