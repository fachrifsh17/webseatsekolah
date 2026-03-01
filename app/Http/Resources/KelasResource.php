<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class KelasResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'nama_kelas'    => $this->nama_kelas,
            'is_active'     => (bool) $this->is_active,
            
            'total_siswa'   => $this->siswa_count ?? 0,

            // Data Tingkatan
            'tingkatan' => $this->whenLoaded('tingkatan', function () {
                return $this->tingkatan ? [
                    'id'   => $this->tingkatan->id,
                    'nama' => $this->tingkatan->nama_tingkatan,
                ] : null;
            }),

            // Data Wali Kelas (diambil dari tabel pivot kelas_wali_kelas)
            'wali_kelas' => $this->whenLoaded('waliKelas', function () {
                // Logika pivot diperbarui sesuai struktur database
                $waliAktif = $this->waliKelas->where('pivot.is_active', true)->first();
                
                return $waliAktif ? [
                    'id'   => $waliAktif->id,
                    'nama' => $waliAktif->nama,
                    // Mengambil ID Tahun Ajaran dari pivot
                    'tahun_ajaran_id' => $waliAktif->pivot->tahun_ajaran_id,
                ] : null;
            }),

            'jurusan' => $this->whenLoaded('jurusan', function () {
                return $this->jurusan ? [
                    'id'   => $this->jurusan->id,
                    'nama' => $this->jurusan->nama_jurusan ?? null,
                ] : null;
            }),

            'created_at' => $this->created_at?->format('d-m-Y H:i'),
            'updated_at' => $this->updated_at?->format('d-m-Y H:i'),
        ];
    }
}