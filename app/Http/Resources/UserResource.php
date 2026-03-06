<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        $fotoUrl = asset('images/default-avatar.png');
        $guruData = $this->relationLoaded('guruStaf') ? $this->guruStaf : ($this->relationLoaded('guru') ? $this->guru : null);
        
        if ($guruData && $guruData->foto) {
            // Menyesuaikan path untuk Guru
            $path = str_replace('uploads/guru/', '', $guruData->foto);
            $fotoUrl = asset('uploads/guru/' . $path);
        } elseif ($this->relationLoaded('siswa') && $this->siswa && $this->siswa->foto) {
            // Menyesuaikan path untuk Siswa
            $path = str_replace('uploads/siswa/foto/', '', $this->siswa->foto);
            $fotoUrl = asset('uploads/siswa/foto/' . $path);
        }

        return [
            'id'           => $this->id,
            'username'     => $this->username,
            'is_active'    => (int) $this->is_active,
            'current_role' => $this->current_role, 

            'foto' => $fotoUrl,

            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(fn($role) => [
                    'id'   => $role->id,
                    'nama' => $role->role_name ?? $role->nama,
                ])->values();
            }),

            'guru' => $this->when($guruData, function () use ($guruData) {
                return [
                    'id'      => $guruData->id,
                    'nip'     => $guruData->nip,
                    'nama'    => $guruData->nama,
                    'jabatan' => $guruData->jabatan_fungsional,
                    'jabatan_struktural' => $guruData->strukturJabatan
                        ? $guruData->strukturJabatan->map(fn($sj) => $sj->jabatan?->nama_jabatan)->filter()->values()
                        : [],
                    'kelas_wali' => $guruData->kelas instanceof \Illuminate\Support\Collection 
                        ? $guruData->kelas->first()?->nama_kelas 
                        : null,
                ];
            }),

            'siswa' => $this->when($this->relationLoaded('siswa') && $this->siswa, function () {
                return [
                    'id'    => $this->siswa->id,
                    'nis'   => $this->siswa->nis,
                    'nama'  => $this->siswa->nama_lengkap,
                    'kelas' => $this->siswa->kelas instanceof \Illuminate\Support\Collection 
                        ? $this->siswa->kelas->first()?->nama_kelas 
                        : ($this->siswa->kelas?->nama_kelas ?? null),
                ];
            }),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}