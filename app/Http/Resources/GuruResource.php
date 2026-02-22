<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class GuruResource extends JsonResource
{
    public function toArray($request): array
    {
        $isAdmin = $request->is('api/admin/*');

        // 1. Definisikan URL foto utama agar bisa dipakai berulang
        $fullFotoUrl = $this->foto 
            ? asset(Storage::url($this->foto)) 
            : asset('images/default-avatar.png');

        return [
            'id'                 => $this->id,
            'nama'               => $this->nama,
            'nip'                => $this->nip,
            'jabatan_fungsional' => $this->jabatan_fungsional,
            'status_kepegawaian' => $this->status_kepegawaian,
            'foto_url'           => $fullFotoUrl,
            
            'jurusan' => $this->whenLoaded('jurusan', function() {
                return [
                    'id'           => $this->jurusan->id,
                    'nama_jurusan' => $this->jurusan->nama_jurusan,
                ];
            }),

            // Data khusus Admin
            $this->mergeWhen($isAdmin, [
                'nuptk'      => $this->nuptk,
                'is_active'  => (int) $this->is_active,
                
                // 2. User dibuat sangat simpel: Hanya Username & Foto
                'user' => $this->whenLoaded('user', function() use ($fullFotoUrl) {
                    return [
                        'username' => $this->user->username,
                    ];
                }),

                'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            ]),
        ];
    }
}