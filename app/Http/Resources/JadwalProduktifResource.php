<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JadwalProduktifResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'judul'             => $this->judul,
            'penjelasan_jadwal' => $this->penjelasan_jadwal,
            'jurusan'           => [
                'id'   => $this->jurusan_id,
                'nama' => $this->jurusan->nama_jurusan ?? null,
            ],
            'tahun_ajaran'      => [
                'id'       => $this->tahun_ajaran_id,
                'nama'     => $this->tahunAjaran->nama ?? null,
                'semester' => $this->tahunAjaran->semester ?? null,
            ],
            'file_url'          => $this->file_jadwal_path ? asset('storage/' . $this->file_jadwal_path) : null,
            'created_at'        => $this->created_at ? $this->created_at->format('d-m-Y H:i') : null,
            'updated_at'        => $this->updated_at ? $this->updated_at->format('d-m-Y H:i') : null,
        ];
    }
}