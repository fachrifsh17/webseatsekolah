<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class PresensiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isWali = $request->is('*siswa-wali*');

        /**
         * $this = Model PresensiDetail
         * $this->header = Model Presensi
         */
        $header = $this->header; 

        return [
            'id' => $this->id, // ID dari presensi_detail
            
            'tanggal' => $header && $header->tanggal ? Carbon::parse($header->tanggal)->format('Y-m-d') : null,
            'status' => $this->status,
            'keterangan' => $this->keterangan,
            
            $this->mergeWhen($isWali, [
                'siswa_id' => (string) $this->siswa_id,
                'nama_lengkap' => $this->siswa?->nama_lengkap,
                'kelas' => $header?->kelas?->nama_kelas ?? $header?->kelas_id, 
            ]),

            $this->mergeWhen(!$isWali, [
                'siswa' => [
                    'id' => (string) $this->siswa_id,
                    'nama' => $this->siswa?->nama_lengkap,
                    'kelas' => $header?->kelas?->nama_kelas ?? $header?->kelas_id,
                ],
                
                // SEBELUMNYA: Lewat kelasWali (Lama/Boros Query)
                // SEKARANG: Langsung ambil dari relasi guru di header (Cepat)
                'guru' => [
                    'id' => $header?->guru_id,
                    'nama' => $header?->guru?->nama ?? '-',
                ],
                
                'semester' => [
                    'nama' => $header?->semester?->nama ?? '-',
                    'tahun_ajaran_id' => $header?->semester?->tahun_ajaran_id,
                ],
            ]),
        ];
    }
}