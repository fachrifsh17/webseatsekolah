<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        // Tentukan Path Foto (Cek guruStaf atau guru)
        $fotoPath = null;
        $guruData = $this->relationLoaded('guruStaf') ? $this->guruStaf : ($this->relationLoaded('guru') ? $this->guru : null);
        
        if ($guruData) {
            $fotoPath = $guruData->foto;
        } elseif ($this->relationLoaded('siswa') && $this->siswa) {
            $fotoPath = $this->siswa->foto;
        }

        // Cek apakah ini request login (untuk sembunyikan foto jika diminta sebelumnya)
        $isLoginRequest = $request->is('*login*');

        return [
            'id'           => $this->id,
            'username'     => $this->username,
            'is_active'    => (int) $this->is_active,
            'current_role' => $this->current_role, 

            // Munculkan foto hanya jika bukan login, atau sesuaikan keinginanmu
            'foto' => $fotoPath ? asset('storage/' . $fotoPath) : asset('images/default-avatar.png'),

            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(fn($role) => [
                    'id'   => $role->id,
                    'nama' => $role->role_name ?? $role->nama,
                ])->values();
            }),

            // Data Guru (Gunakan variable $guruData yang sudah dicek di atas)
            'guru' => $this->when($guruData, function () use ($guruData) {
                return [
                    'id'      => $guruData->id,
                    'nip'     => $guruData->nip,
                    'nama'    => $guruData->nama,
                    'jabatan' => $guruData->jabatan_fungsional,
                    'jabatan_struktural' => $guruData->strukturJabatan
                        ? $guruData->strukturJabatan->map(fn($sj) => $sj->jabatan?->nama_jabatan)->filter()->values()
                        : [],
                    'kelas_wali' => $guruData->kelas?->nama_kelas,
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

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}