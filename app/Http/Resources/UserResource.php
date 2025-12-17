<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        // Sesuaikan dengan kolom di tabel users kamu (username, nama_lengkap)
        return [
            'id'           => $this->id,
            'username'     => $this->username,
            'nama_lengkap' => $this->nama_lengkap,
            'role_id'      => $this->role_id,
            
            // Relasi ke Role (Gunakan role tunggal sesuai tabel role_id kamu)
            'role' => $this->whenLoaded('role', function () {
                return [
                    'id'   => $this->role->id,
                    'nama' => $this->role->nama_role, // Sesuaikan nama kolom di tabel roles
                ];
            }),
            
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}