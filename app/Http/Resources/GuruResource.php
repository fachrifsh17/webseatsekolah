<?php

namespace App\Http\Resources;

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
            'foto_url' => $this->foto ? asset('storage/'.$this->foto) : null,
            'jurusan' => new JurusanResource($this->whenLoaded('jurusan')),
        ];
    }
}
