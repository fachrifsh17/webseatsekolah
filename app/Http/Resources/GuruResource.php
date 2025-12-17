<?php

namespace App\Http\Resources;

use App\Http\Resources\JurusanResource;
use Illuminate\Http\Resources\Json\JsonResource;

class GuruResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'nip' => $this->nip,
            'nuptk' => $this->nuptk,
            'nama' => $this->nama,
            'jabatan_fungsional' => $this->jabatan_fungsional,
            'status_kepegawaian' => $this->status_kepegawaian,
            'bidang_studi' => $this->bidang_studi ?? null,
            'jurusan_id' => $this->jurusan_id,
            'jurusan' => new JurusanResource($this->whenLoaded('jurusan')),
            'foto_url' => $this->foto ? asset('storage/' . $this->foto) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}