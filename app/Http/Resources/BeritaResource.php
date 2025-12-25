<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BeritaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'judul'             => $this->judul,
            'isi_berita'        => $this->isi_berita,
            'tanggal_publikasi' => $this->tanggal_publikasi,
            'kategori_id'       => $this->kategori_id,
            // Menampilkan data kategori secara langsung tanpa pengecekan load
            'kategori'          => [
                'id'   => $this->kategori->id ?? null,
                'nama' => $this->kategori->nama_kategori ?? null,
            ],
            // Mengubah path foto menjadi URL lengkap
            'foto'              => $this->foto ? asset('storage/' . $this->foto) : null,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}