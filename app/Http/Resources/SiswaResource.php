<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SiswaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'user_id'        => $this->user_id,
            'nis'            => $this->nis,
            'nama_lengkap'   => $this->nama_lengkap,
            'tempat_lahir'   => $this->tempat_lahir,  // Baru ditambahkan sesuai image_096627.png
            'tanggal_lahir'  => $this->tanggal_lahir, // Baru ditambahkan sesuai image_096627.png
            'jenis_kelamin'  => $this->jenis_kelamin, 
            'kelas'          => [
                'id'   => $this->kelas_id,
                'nama' => $this->kelas->nama_kelas ?? null,
            ],
            'jurusan'        => [
                'id'   => $this->jurusan_id,
                'nama' => $this->jurusan->nama_jurusan ?? null,
            ],
            'orangtua_id'    => $this->orangtua_id,
            'foto'           => $this->foto,
            'foto_url'       => $this->foto ? url(Storage::url($this->foto)) : null,
            'no_telp_siswa'  => $this->no_telp_siswa, 
            'alamat'         => $this->alamat,        
            'status_aktif'   => $this->status_aktif,  
            'total_poin'     => ($this->poin_siswa_sum_poin_positif ?? 0) - ($this->poin_siswa_sum_poin_negatif ?? 0),
            'created_at'     => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at'     => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }
}