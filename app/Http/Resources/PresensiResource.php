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

        // Pastikan relasi ke header ('presensi') dimuat (eager load) di controller
        $header = $this->presensi; 

        return [
            'id' => $this->id, // ID dari presensi_detail
            
            // Mengambil tanggal dari tabel header (presensi)
            'tanggal' => $header && $header->tanggal ? Carbon::parse($header->tanggal)->format('Y-m-d') : null,
            
            // Status dan Keterangan dari tabel detail (presensi_detail)
            'status' => $this->status,
            'keterangan' => $this->keterangan,
            
            $this->mergeWhen($isWali, [
                'siswa_id' => (string) $this->siswa_id,
                'nama_lengkap' => $this->siswa?->nama_lengkap,
                // Mengambil dari relasi header ke kelas
                'kelas' => $header?->kelas?->nama_kelas,
            ]),

            $this->mergeWhen(!$isWali, [
                'siswa' => [
                    'id' => (string) $this->siswa_id,
                    'nama' => $this->siswa?->nama_lengkap,
                    // Mengambil dari relasi header ke kelas
                    'kelas' => $header?->kelas?->nama_kelas,
                ],
                // Mengambil dari relasi header ke guru
                'guru' => $header?->guruStaf?->nama,
                // --- PERUBAHAN DI SINI ---
                'semester' => [
                    // Mengambil dari relasi header ke semester
                    'nama' => $header?->semester?->nama ?? '-',
                    'tahun_ajaran_id' => $header?->semester?->tahun_ajaran_id,
                ],
            ]),
        ];
    }
}