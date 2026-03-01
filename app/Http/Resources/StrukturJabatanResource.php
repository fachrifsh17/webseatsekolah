<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class StrukturJabatanResource extends JsonResource
{
    public function toArray($request): array
    {
        // Cek apakah ini akses dari admin panel
        $isAdmin = $request->is('api/admin/*');

        return [
            'id'     => $this->id,
            'urutan' => (int) $this->urutan_tampil,
            
            // Data Jabatan
            'jabatan' => [
                'id'   => $this->jabatan_id,
                'nama' => $this->jabatan->nama_jabatan ?? null,
                'slug' => $this->jabatan->slug ?? null,
                // Keterangan hanya muncul untuk admin (opsional)
                'keterangan' => $this->when($isAdmin, $this->jabatan->keterangan),
            ],

            // Pastikan nama relasi 'guruStaf' sesuai dengan Model/Controller Anda
            'pejabat' => $this->whenLoaded('guruStaf', function () use ($isAdmin) {
                return [
                    'id'   => $this->guruStaf->id,
                    'nama' => $this->guruStaf->nama,
                    'nip'  => $this->guruStaf->nip,
                    // URL Foto yang siap pakai oleh frontend
                    'foto' => $this->guruStaf->foto 
                        ? url(Storage::url($this->guruStaf->foto)) 
                        : url('images/default-avatar.png'),
                    // Data khusus admin
                    'status_kepegawaian' => $this->when($isAdmin, $this->guruStaf->status_kepegawaian),
                ];
            }),

            // --- TAMBAHAN LOGIKA TTD HANYA UNTUK ADMIN ---
            'url_ttd' => $this->when($isAdmin && $this->file_ttd, function () {
                // Mengambil URL lengkap gambar tanda tangan
                return $this->file_ttd ? url(Storage::url($this->file_ttd)) : null;
            }),
            // ---------------------------------------------

            'periode' => $this->periode_mulai instanceof \DateTimeInterface 
                ? $this->periode_mulai->format('Y-m-d') 
                : $this->periode_mulai,

            // Metadata yang hanya dibutuhkan Admin untuk keperluan audit/form
            'created_at' => $this->when($isAdmin, $this->created_at?->format('Y-m-d H:i:s')),
            'updated_at' => $this->when($isAdmin, $this->updated_at?->format('Y-m-d H:i:s')),
        ];
    }
}