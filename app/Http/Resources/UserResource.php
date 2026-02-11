<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'        => $this->id,
            'username'  => $this->username,
            'is_active' => (int) $this->is_active,

            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(function ($role) {
                    return [
                        'id'   => $role->id,
                        'nama' => $role->role_name,
                    ];
                })->values();
            }),

            'guru' => $this->when($this->relationLoaded('guruStaf') && $this->guruStaf, function () {
                return [
                    'id'      => $this->guruStaf->id,
                    'nip'     => $this->guruStaf->nip,
                    'nama'    => $this->guruStaf->nama,
                    'jabatan' => $this->guruStaf->jabatan_fungsional,
                    'jabatan_struktural' => $this->guruStaf->strukturJabatan
                        ->map(fn($sj) => $sj->jabatan?->nama_jabatan)
                        ->filter()
                        ->values(),
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
                if ($this->orangtua instanceof \Illuminate\Support\Collection) {
                    return $this->orangtua->map(function ($o) {
                        return [
                            'id'           => $o->id,
                            'nama_lengkap' => $o->nama_lengkap,
                            'telepon'      => $o->telepon,
                        ];
                    })->values();
                }

                return [
                    'id'           => $this->orangtua->id,
                    'nama_lengkap' => $this->orangtua->nama_lengkap,
                    'telepon'      => $this->orangtua->telepon,
                ];
            }),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
