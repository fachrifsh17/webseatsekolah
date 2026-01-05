<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class BeritaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'judul' => $this->judul,
            'isi_berita' => $this->isi_berita,
            'tanggal_publikasi' => $this->formatTanggalPublikasi(),
            'foto' => $this->foto,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }

    protected function formatTanggalPublikasi(): ?string
    {
        $value = $this->tanggal_publikasi;

        if (! $value) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d'); 
        }
        try {
            $dt = Carbon::parse($value);
            return $dt->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
