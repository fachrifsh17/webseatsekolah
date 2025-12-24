<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KelasResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'nama_kelas'  => $this->nama_kelas,
            'jurusan'     => [
                'id'   => $this->jurusan_id,
                'nama' => $this->jurusan->nama_jurusan ?? null,
            ],
            'tahun_ajaran' => [
                'id'       => $this->tahun_ajaran_id,
                'nama'     => $this->tahunAjaran->nama ?? null,
                'semester' => $this->tahunAjaran->semester ?? null,
            ],
            'created_at'  => $this->created_at ? $this->created_at->format('d-m-Y H:i') : null,
            'updated_at'  => $this->updated_at ? $this->updated_at->format('d-m-Y H:i') : null,
        ];
    }
}