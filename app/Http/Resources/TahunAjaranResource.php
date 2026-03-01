<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\KurikulumResource;
use App\Http\Resources\SemesterResource; // Pastikan Resource ini ada

class TahunAjaranResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            // 'semester' => $this->semester, // Field ini mungkin tidak diperlukan lagi jika menggunakan tabel semesters
            'is_active' => (bool) $this->is_active,
            
            'kurikulum' => new KurikulumResource($this->whenLoaded('kurikulum')),
            
            // --- PERUBAHAN DI SINI ---
            // Memuat relasi ke model Semester
            'semesters' => SemesterResource::collection($this->whenLoaded('semesters')),

            'created_at' => $this->created_at?->format('d-m-Y H:i'),
            'updated_at' => $this->updated_at?->format('d-m-Y H:i'),
        ];
    }
}