<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class JamSekolahResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tahun_ajaran' => [
                'id' => $this->tahun_ajaran_id,
                'nama' => $this->tahunAjaran->nama ?? null,
            ],
            'semester' => $this->semester,
            'keterangan' => $this->keterangan,
            'file_url' => $this->file_path ? url(Storage::url($this->file_path)) : null,
            'created_at' => $this->created_at->format('d-m-Y H:i'),
        ];
    }
}