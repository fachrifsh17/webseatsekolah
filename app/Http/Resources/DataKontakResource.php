<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DataKontakResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            // Data Alamat Terpisah
            'alamat_jalan'     => $this->alamat_jalan,
            'desa_kelurahan'   => $this->desa_kelurahan,
            'kecamatan'        => $this->kecamatan,
            'kabupaten_kota'   => $this->kabupaten_kota,
            'provinsi'         => $this->provinsi,
            
            // Helper: Alamat Lengkap Gabungan (untuk kemudahan Front-end)
            'alamat_lengkap'   => "{$this->alamat_jalan}, {$this->desa_kelurahan}, Kec. {$this->kecamatan}, {$this->kabupaten_kota}, {$this->provinsi}",
            
            'telepon'          => $this->telepon,       
            'email'            => $this->email_resmi,   
            'maps_embed_code'  => $this->peta_embed_code,
            'updated_at'       => $this->updated_at ? $this->updated_at->format('d-m-Y H:i') : null,
        ];
    }
}