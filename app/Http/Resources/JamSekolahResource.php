<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JamSekolahResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            // --- PERUBAHAN DI SINI ---
            'semester_id'   => $this->semester_id,
            'semester'      => $this->whenLoaded('semester', function () {
                return [
                    'id'              => $this->semester?->id,
                    'nama'            => $this->semester?->nama,
                    'tahun_ajaran_id' => $this->semester?->tahun_ajaran_id,
                    'aktif'           => (bool) ($this->semester?->is_active ?? false),
                ];
            }),
            'hari'          => $this->hari,
            'jam_ke'        => $this->jam_ke,
            'waktu_mulai'   => $this->waktu_mulai ? $this->waktu_mulai->format('H:i') : null,
            'waktu_selesai' => $this->waktu_selesai ? $this->waktu_selesai->format('H:i') : null,
            'jenis'         => $this->jenis,
            'keterangan'    => $this->keterangan,
            'created_at'    => $this->created_at?->format('d-m-Y H:i'),
            'updated_at'    => $this->updated_at?->format('d-m-Y H:i'),
        ];
    }
}