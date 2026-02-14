<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class PengumumanResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'judul' => $this->judul,
            'isi_pengumuman' => $this->isi_pengumuman,
            // Format tanggal agar hanya YYYY-MM-DD
            'tanggal_publikasi' => $this->tanggal_publikasi ? Carbon::parse($this->tanggal_publikasi)->format('Y-m-d') : null,
            'penting' => (bool) $this->penting,
        ];
    }
}