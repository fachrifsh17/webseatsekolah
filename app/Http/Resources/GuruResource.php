<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class GuruResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'user_id'            => $this->user_id,
            'user'               => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'nip'                => $this->nip,
            'nuptk'              => $this->nuptk,
            'nama'               => $this->nama,
            'jabatan_fungsional' => $this->jabatan_fungsional,
            'status_kepegawaian' => $this->status_kepegawaian,
            'jurusan_id'         => $this->jurusan_id,
            'jurusan'            => $this->whenLoaded('jurusan', fn () => new JurusanResource($this->jurusan)),
            'is_active'          => isset($this->is_active) ? (int) $this->is_active : null,
            'foto_url'           => $this->foto ? Storage::url($this->foto) : null,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
