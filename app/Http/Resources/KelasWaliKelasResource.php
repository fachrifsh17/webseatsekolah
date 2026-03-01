<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KelasWaliKelasResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'is_active'   => (bool) $this->is_active,
            // --- PERUBAHAN DI SINI ---
            'semester_id' => $this->semester_id,
            
            // Relasi ke tabel kelas
            'kelas' => $this->whenLoaded('kelas', function () {
                return [
                    'id'   => $this->kelas->id,
                    'nama' => $this->kelas->nama_kelas,
                ];
            }),
            
            // Relasi ke tabel guru staf
            'guru_staf' => $this->whenLoaded('guruStaf', function () {
                return [
                    'id'   => $this->guruStaf->id,
                    'nama' => $this->guruStaf->nama,
                    'nip'  => $this->guruStaf->nip,
                ];
            }),
            
            // --- PERUBAHAN DI SINI ---
            // Relasi ke tabel semester
            'semester' => $this->whenLoaded('semester', function () {
                return [
                    'id'              => $this->semester->id,
                    'nama'            => $this->semester->nama,
                    'tahun_ajaran_id' => $this->semester->tahun_ajaran_id,
                ];
            }),

            'created_at' => $this->created_at?->format('d-m-Y H:i'),
            'updated_at' => $this->updated_at?->format('d-m-Y H:i'),
        ];
    }
}