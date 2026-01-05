<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class PesanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama_lengkap' => $this->nama_lengkap,
            'email' => $this->email,
            'subjek' => $this->subjek,
            'pesan' => $this->isi_pesan ?? $this->pesan,
            'status' => $this->status,
            'tanggal_masuk' => $this->formatTanggalMasuk(),
        ];
    }

    protected function formatTanggalMasuk(): ?string
    {
        if ($this->tanggal_kirim) {
            try {
                return Carbon::parse($this->tanggal_kirim)->format('d-m-Y');
            } catch (\Throwable $e) {
                return null;
            }
        }

        if ($this->created_at) {
            try {
                return Carbon::parse($this->created_at)->format('d-m-Y H:i');
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }
}
