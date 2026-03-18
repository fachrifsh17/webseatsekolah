<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class StrukturJabatanResource extends JsonResource
{
    public function toArray($request): array
    {
        $isAdmin = $request->is('api/admin/*');

        return [
            'id'     => $this->id,
            'urutan' => (int) $this->urutan_tampil,
            
            'jabatan' => [
                'id'   => $this->jabatan_id,
                'nama' => $this->jabatan->nama_jabatan ?? null,
                'slug' => $this->jabatan->slug ?? null,
                'keterangan' => $this->when($isAdmin, $this->jabatan->keterangan ?? null),
            ],

            'pejabat' => $this->whenLoaded('guruStaf', function () use ($isAdmin) {
                $fotoUrl = asset('images/default-avatar.png');

                if ($this->guruStaf->foto) {
                    $fileName = basename($this->guruStaf->foto);
                    $fotoUrl = asset('uploads/guru/' . $fileName);
                }

                return [
                    'id'   => $this->guruStaf->id,
                    'nama' => $this->guruStaf->nama,
                    'nip'  => $this->guruStaf->nip,
                    'foto_url' => $fotoUrl,
                    'status_kepegawaian' => $this->when($isAdmin, $this->guruStaf->status_kepegawaian),
                ];
            }),

            'url_ttd' => $this->file_ttd ? url("api/admin/struktur-jabatan/ttd/{$this->id}") : null,

            'periode' => $this->periode_mulai ? Carbon::parse($this->periode_mulai)->format('Y-m-d') : null,

            'created_at' => $this->when($isAdmin, $this->created_at?->format('Y-m-d H:i:s')),
            'updated_at' => $this->when($isAdmin, $this->updated_at?->format('Y-m-d H:i:s')),
        ];
    }
}