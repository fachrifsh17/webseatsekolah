<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

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
                'keterangan' => $this->when($isAdmin, $this->jabatan->keterangan),
            ],

            'pejabat' => $this->whenLoaded('guruStaf', function () use ($isAdmin) {
                return [
                    'id'   => $this->guruStaf->id,
                    'nama' => $this->guruStaf->nama,
                    'nip'  => $this->guruStaf->nip,
                    
                    // Foto tetap menggunakan path publik (uploads/guru)
                    'foto' => $this->guruStaf->foto 
                        ? asset('uploads/guru/' . str_replace('uploads/guru/', '', $this->guruStaf->foto)) 
                        : asset('images/default-avatar.png'),
                    
                    'status_kepegawaian' => $this->when($isAdmin, $this->guruStaf->status_kepegawaian),
                ];
            }),

            // Perubahan: TTD mengarah ke route privat di Controller
            'url_ttd' => $this->when($isAdmin && $this->file_ttd, function () {
                return url("api/struktur-jabatan/ttd/{$this->id}");
            }),

            'periode' => $this->periode_mulai instanceof \DateTimeInterface 
                ? $this->periode_mulai->format('Y-m-d') 
                : $this->periode_mulai,

            'created_at' => $this->when($isAdmin, $this->created_at?->format('Y-m-d H:i:s')),
            'updated_at' => $this->when($isAdmin, $this->updated_at?->format('Y-m-d H:i:s')),
        ];
    }
}