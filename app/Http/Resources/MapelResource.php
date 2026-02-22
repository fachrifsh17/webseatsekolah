<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MapelResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'nama_mapel' => $this->nama_mapel,
            'jurusan_id' => $this->jurusan_id,
            'tipe_mapel' => $this->tipe_mapel,
            'kategori_mapel' => $this->kategori_mapel,
            'is_active' => (bool) $this->is_active, 
            
            // Menampilkan hanya nama jurusan jika relasi dimuat
            'jurusan' => $this->whenLoaded('jurusan', function() {
                return $this->jurusan->nama_jurusan;
            }),

            // Format tanggal rapi: 15-02-2026 18:49
            'created_at' => $this->created_at ? $this->created_at->format('d-m-Y H:i') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('d-m-Y H:i') : null,
        ];
    }
}