<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PresensiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'siswa'        => [
                'id'   => $this->siswa_id,
                'nama' => $this->siswa->nama_lengkap ?? null,
                'nis'  => $this->siswa->nis ?? null,
            ],
            'tahun_ajaran' => [
                'id'       => $this->tahun_ajaran_id,
                'nama'     => $this->tahunAjaran->nama ?? null,
                'semester' => $this->tahunAjaran->semester ?? null,
            ],
            'tanggal'      => $this->tanggal,
            'status'       => $this->status,
            'keterangan'   => $this->keterangan,
            'created_at'   => $this->created_at ? $this->created_at->format('d-m-Y H:i') : null,
            'updated_at'   => $this->updated_at ? $this->updated_at->format('d-m-Y H:i') : null,
        ];
    }
}