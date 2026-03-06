<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KelasResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'nama_kelas'    => $this->nama_kelas,
            'is_active'     => (bool) $this->is_active,
            'total_siswa'   => $this->siswa_count ?? 0,

            'tingkatan' => $this->whenLoaded('tingkatan', function () {
                return $this->tingkatan ? [
                    'id'   => $this->tingkatan->id,
                    'nama' => $this->tingkatan->nama_tingkatan,
                ] : null;
            }),

            'wali_kelas' => $this->whenLoaded('waliKelas', function () {
                $waliAktif = $this->waliKelas->where('pivot.is_active', 1)->first();
                
                return $waliAktif ? [
                    'id'   => $waliAktif->id,
                    'nama' => $waliAktif->nama,
                    'semester_id' => $waliAktif->pivot->semester_id,
                ] : null;
            }),

            'jurusan' => $this->whenLoaded('jurusan', function () {
                return $this->jurusan ? [
                    'id'   => $this->jurusan->id,
                    'nama' => $this->jurusan->nama_jurusan,
                ] : null;
            }),

            'created_at' => $this->created_at?->format('d-m-Y H:i'),
            'updated_at' => $this->updated_at?->format('d-m-Y H:i'),
        ];
    }
}