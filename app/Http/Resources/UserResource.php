<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        $isGuru = $this->roles->contains('role_name', 'Guru'); // Cek apakah salah satu peran adalah 'Guru'

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            
            // Relasi ke Peran (Roles)
            'roles' => $this->whenLoaded('roles', function () {
                // Menggunakan RoleResource yang sudah kita sesuaikan
                return RoleResource::collection($this->roles); 
            }),
            // Data Guru/Staf hanya dimuat jika pengguna memiliki peran 'Guru'
            'pegawai' => $this->whenLoaded('pegawai', function () use ($isGuru) {
                if ($isGuru) {
                    return new GuruResource($this->pegawai);
                }
                return null;
            }),
            
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}