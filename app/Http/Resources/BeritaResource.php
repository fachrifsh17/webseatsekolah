<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class BeritaResource extends JsonResource
{
    public function toArray($request): array
    {
        // Cek apakah request datang dari route admin atau bukan
        $isAdmin = $request->is('api/admin/*') || $request->is('admin/*');

        return [
            'id' => $this->id,
            'judul' => $this->judul,
            'isi_berita' => $this->isi_berita,
            'tanggal_publikasi' => $this->formatTanggalPublikasi(),
            
            // Jika Admin, kirim path asli untuk input form. 
            // Jika Publik, kirim URL lengkap untuk tampilan.
            'foto' => $this->foto, 
            'foto_url' => $this->foto ? asset('storage/' . $this->foto) : null,
            
            // Data tambahan yang biasanya hanya dibutuhkan Admin
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            
            // Kita bisa menyembunyikan updated_at untuk Publik menggunakan conditional
            'updated_at' => $this->when($isAdmin, $this->updated_at?->format('Y-m-d H:i:s')),
            
            // Data bantuan untuk tampilan publik
            'tanggal_human' => $this->when(!$isAdmin, function() {
                return $this->tanggal_publikasi ? Carbon::parse($this->tanggal_publikasi)->translatedFormat('d F Y') : null;
            }),
        ];
    }

    protected function formatTanggalPublikasi(): ?string
    {
        $value = $this->tanggal_publikasi;
        if (!$value) return null;
        
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }
}