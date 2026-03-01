<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class KalenderAkademikResource extends JsonResource
{
    public function toArray($request): array
    {
        $isAdmin = $request->is('api/admin/*');

        return [
            'id'              => $this->id,
            'kegiatan'        => $this->kegiatan,
            
            // Menambahkan format Y-m-d (Tahun-Bulan-Tanggal)
            'tanggal_mulai'   => $this->tanggal_mulai instanceof \DateTimeInterface 
                ? $this->tanggal_mulai->format('Y-m-d') 
                : Carbon::parse($this->tanggal_mulai)->format('Y-m-d'),

            'tanggal_selesai' => $this->tanggal_selesai instanceof \DateTimeInterface 
                ? $this->tanggal_selesai->format('Y-m-d') 
                : Carbon::parse($this->tanggal_selesai)->format('Y-m-d'),

            'kategori'        => $this->kategori,
            
            // --- PERUBAHAN DI SINI ---
            // Mengambil dari relasi semester
            'semester'        => $this->semester->nama ?? null,
            // Jika ingin menampilkan nama tahun ajaran melalui semester
            'tahun_ajaran'    => $this->semester->tahunAjaran->nama ?? null,

            $this->mergeWhen($isAdmin, [
                // --- PERUBAHAN DI SINI ---
                'semester_id'     => $this->semester_id,
                'created_at'      => $this->created_at?->format('Y-m-d H:i:s'),
                'updated_at'      => $this->updated_at?->format('Y-m-d H:i:s'),
            ]),
        ];
    }
}