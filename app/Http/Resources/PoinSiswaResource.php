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
                'id'   => $this->siswa_id,
                'nama' => $this->siswa?->nama_lengkap ?? $this->siswa?->nama,
                'nis'  => $this->siswa?->nis,
                'kelas'=> $this->siswa?->kelas?->nama_kelas,
            ],
            'guru_pelapor' => [
                'id'   => $this->guru_staf_id,
                'nama' => $this->guruStaf?->nama,
            ],
            'tahun_ajaran' => $this->tahunAjaran?->nama ?? $this->tahunAjaran?->tahun_ajaran,
            'indikator'    => $this->indikator,
            'poin_positif' => (int) ($this->poin_positif ?? 0),
            'poin_negatif' => (int) ($this->poin_negatif ?? 0),
            
            // Field baru untuk akumulasi dari kelas 10-12
            'total_kumulatif_positif' => (int) ($this->total_kumulatif_positif ?? 0),
            'total_kumulatif_negatif' => (int) ($this->total_kumulatif_negatif ?? 0),
            
            // Poin bersih pada baris transaksi ini
            'total_poin_transaksi'   => (int) (($this->poin_positif ?? 0) - ($this->poin_negatif ?? 0)),

            'tanggal'      => $this->tanggal instanceof Carbon
                ? $this->tanggal->format('d-m-Y')
                : $this->tanggal,

            'created_at'   => $this->created_at instanceof Carbon
                ? $this->created_at->format('d-m-Y H:i')
                : $this->created_at,

            'updated_at'   => $this->updated_at instanceof Carbon
                ? $this->updated_at->format('d-m-Y H:i')
                : $this->updated_at,
        ];
    }
}