<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        // 1. Tentukan Path Foto Utama
        $fotoPath = null;
        if ($this->relationLoaded('guruStaf') && $this->guruStaf) {
            $fotoPath = $this->guruStaf->foto;
        } elseif ($this->relationLoaded('siswa') && $this->siswa) {
            $fotoPath = $this->siswa->foto;
        }

        return [
            'id'           => $this->id,
            'username'     => $this->username,
            'is_active'    => (int) $this->is_active,
            
            // --- PENAMBAHAN CURRENT ROLE ---
            // Memberikan informasi role yang sedang aktif saat ini
            'current_role' => $this->current_role, 

            // SATU-SATUNYA SUMBER FOTO (Level Atas)
            'foto'         => $fotoPath 
                ? asset('storage/' . $fotoPath) 
                : asset('images/default-avatar.png'),

            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(fn($role) => [
                    'id'   => $role->id,
                    'nama' => $role->role_name ?? $role->nama, // antisipasi beda nama kolom
                ])->values();
            }),

            'guru' => $this->when($this->relationLoaded('guruStaf') && $this->guruStaf, function () {
                return [
                    'id'      => $this->guruStaf->id,
                    'nip'     => $this->guruStaf->nip,
                    'nama'    => $this->guruStaf->nama,
                    'jabatan' => $this->guruStaf->jabatan_fungsional,
                    'jabatan_struktural' => $this->guruStaf->strukturJabatan
                        ? $this->guruStaf->strukturJabatan->map(fn($sj) => $sj->jabatan?->nama_jabatan)->filter()->values()
                        : [],
                    'kelas_wali' => $this->guruStaf->kelas?->nama_kelas,
                ];
            }),

            'siswa' => $this->when($this->relationLoaded('siswa') && $this->siswa, function () {
                return [
                    'id'    => $this->siswa->id,
                    'nis'   => $this->siswa->nis,
                    'nama'  => $this->siswa->nama_lengkap,
                    'kelas' => $this->siswa->kelas?->nama_kelas,
                ];
            }),

            'orangtua' => $this->when($this->relationLoaded('orangtua') && $this->orangtua, function () {
                return $this->orangtua instanceof \Illuminate\Support\Collection
                    ? $this->orangtua->map(fn($o) => [
                        'id' => $o->id, 'nama_lengkap' => $o->nama_lengkap, 'telepon' => $o->telepon
                    ])->values()
                    : [
                        'id' => $this->orangtua->id, 
                        'nama_lengkap' => $this->orangtua->nama_lengkap, 
                        'telepon' => $this->orangtua->telepon
                    ];
            }),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}