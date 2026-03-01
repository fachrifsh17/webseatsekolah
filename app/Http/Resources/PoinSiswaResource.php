<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class PoinSiswaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'siswa'        => [
                'id'    => $this->siswa_id,
                'nama'  => $this->siswa?->nama_lengkap ?? $this->siswa?->nama ?? 'N/A',
                'nis'   => $this->siswa?->nis ?? '-',
                // Asumsi relasi kelasAktif merujuk ke semester_id sekarang
                'kelas' => $this->siswa?->kelasAktif?->kelas?->nama_kelas ?? 'Tanpa Kelas',
            ],
            'guru_pelapor' => [
                'id'   => $this->guru_staf_id,
                'nama' => $this->guruStaf?->nama ?? 'Sistem',
            ],
            // --- PERUBAHAN DI SINI ---
            'semester' => $this->semester?->nama ?? '-',
            
            'indikator'    => $this->indikator,
            'poin_positif' => (int) ($this->poin_positif ?? 0),
            'poin_negatif' => (int) ($this->poin_negatif ?? 0),
            
            'total_kumulatif_positif' => (int) ($this->total_kumulatif_positif ?? 0),
            'total_kumulatif_negatif' => (int) ($this->total_kumulatif_negatif ?? 0),
            
            'total_poin_transaksi'   => (int) (($this->poin_positif ?? 0) - ($this->poin_negatif ?? 0)),

            'tanggal'      => $this->formatDate($this->tanggal, 'd-m-Y'),
            'created_at'   => $this->formatDate($this->created_at, 'd-m-Y H:i'),
            'updated_at'   => $this->formatDate($this->updated_at, 'd-m-Y H:i'),
        ];
    }

    private function formatDate($date, $format)
    {
        if (!$date) return null;
        
        return $date instanceof Carbon 
            ? $date->format($format) 
            : Carbon::parse($date)->format($format);
    }
}