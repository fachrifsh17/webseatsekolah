<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'           => $this->id,
            'username'     => $this->username,
            'nama_lengkap' => $this->nama_lengkap,
            'role_id'      => $this->role_id,
            'is_active'    => (int) $this->is_active,
            
            // Mengambil data role secara langsung tanpa pengecekan whenLoaded
            'role' => $this->role ? [
                'id'   => $this->role->id,
                'nama' => $this->role->role_name,
            ] : null,

            'guru' => $this->when($this->guru, function () {
                return [
                    'nip'     => $this->guru->nip,
                    'jabatan' => $this->guru->jabatan_fungsional,
                ];
            }),

            'siswa' => $this->when($this->siswa, function () {
                return [
                    'nisn'  => $this->siswa->nisn,
                    'kelas' => $this->siswa->kelas?->nama_kelas,
                ];
            }),

            'orangtua' => $this->when($this->orangtua, function () {
                return [
                    'nama_ayah' => $this->orangtua->nama_ayah,
                    'nama_ibu'  => $this->orangtua->nama_ibu,
                ];
            }),
            
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}