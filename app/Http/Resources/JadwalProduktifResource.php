<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JadwalProduktifResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'judul' => $this->judul,
            'penjelasan' => $this->penjelasan_jadwal,
            'jurusan' => [
                'id' => $this->jurusan_id,
                'nama' => $this->jurusan->nama_jurusan ?? null,
            ],
            'guru' => [
                'id' => $this->guru_staf_id,
                'nama' => $this->guruStaf->nama ?? null,
            ],
            'file_url' => $this->file_jadwal_path ? asset('storage/' . $this->file_jadwal_path) : null,
            'created_at' => $this->created_at ? $this->created_at->format('d-m-Y H:i') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('d-m-Y H:i') : null,
        ];
    }
}