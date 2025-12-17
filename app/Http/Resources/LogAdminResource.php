<?php

namespace App\Http\Resources;

// Asumsi Anda memiliki UserResource untuk menampilkan data Admin/User
use App\Http\Resources\UserResource; 
use Illuminate\Http\Resources\Json\JsonResource;

class LogAdminResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'aksi' => $this->aksi,
            
            // Relasi ke User/Admin yang melakukan aksi
            'user' => new UserResource($this->whenLoaded('user')),
            
            // Metadata
            'created_at' => $this->created_at,
        ];
    }
}