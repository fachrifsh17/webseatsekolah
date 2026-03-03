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
         * Penjelasan Relasi:
         * $this = Model PresensiDetail
         * $this->header = Model Presensi
         * $this->header->kelasWali = Model KelasWaliKelas (Pusat datanya di sini)
         */
        $header = $this->header; // Pastikan di model PresensiDetail relasinya bernama 'header'
        $waliRelasi = $header?->kelasWali; 

        return [
            'id' => $this->id, // ID dari presensi_detail
            
            'tanggal' => $header && $header->tanggal ? Carbon::parse($header->tanggal)->format('Y-m-d') : null,
            'status' => $this->status,
            'keterangan' => $this->keterangan,
            
            $this->mergeWhen($isWali, [
                'siswa_id' => (string) $this->siswa_id,
                'nama_lengkap' => $this->siswa?->nama_lengkap,
                // Ambil kelas via kelasWali
                'kelas' => $waliRelasi?->kelas?->nama_kelas,
            ]),

            $this->mergeWhen(!$isWali, [
                'siswa' => [
                    'id' => (string) $this->siswa_id,
                    'nama' => $this->siswa?->nama_lengkap,
                    // Ambil kelas via kelasWali
                    'kelas' => $waliRelasi?->kelas?->nama_kelas,
                ],
                // Ambil guru via kelasWali
                'guru' => $waliRelasi?->guruStaf?->nama,
                
                'semester' => [
                    // Ambil semester via kelasWali
                    'nama' => $waliRelasi?->semester?->nama ?? '-',
                    'tahun_ajaran_id' => $waliRelasi?->semester?->tahun_ajaran_id,
                ],
            ]),
        ];
    }
}