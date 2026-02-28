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
            
            // Kolom Baru yang perlu ditampilkan secara publik/umum
            'no_hp'              => $this->no_hp,
            'jenis_kelamin'      => $this->jenis_kelamin,
            
            'jurusan' => $this->whenLoaded('jurusan', function() {
                return [
                    'id'           => $this->jurusan->id,
                    'nama_jurusan' => $this->jurusan->nama_jurusan,
                ];
            }),

            // Data khusus Admin (Sensitif/Detail)
            $this->mergeWhen($isAdmin, [
                'nuptk'               => $this->nuptk,
                'email'               => $this->email,
                'alamat_lengkap'      => $this->alamat_lengkap,
                'tempat_lahir'        => $this->tempat_lahir,
                'tanggal_lahir'       => $this->tanggal_lahir,
                'agama'               => $this->agama,
                'pendidikan_terakhir' => $this->pendidikan_terakhir,
                'is_active'           => (int) $this->is_active,
                
                // User dibuat sangat simpel: Hanya Username
                'user' => $this->whenLoaded('user', function() {
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