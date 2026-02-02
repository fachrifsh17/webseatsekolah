<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KelasResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'nama_kelas'    => $this->nama_kelas,
            'wali_kelas_id' => $this->wali_kelas_id,

            'wali_kelas' => $this->whenLoaded('waliKelas', function () {
                return $this->waliKelas ? [
                    'id'   => $this->waliKelas->id,
                    'nama' => $this->waliKelas->name ?? $this->waliKelas->nama ?? null,
                ] : null;
            }),

            'jurusan' => $this->whenLoaded('jurusan', function () {
                return $this->jurusan ? [
                    'id'   => $this->jurusan->id,
                    'nama' => $this->jurusan->nama_jurusan ?? null,
                ] : null;
            }),

            'tahun_ajaran' => $this->whenLoaded('tahunAjaran', function () {
                return $this->tahunAjaran ? [
                    'id'       => $this->tahunAjaran->id,
                    'nama'     => $this->tahunAjaran->nama ?? null,
                    'semester' => $this->tahunAjaran->semester ?? null,
                ] : null;
            }),

            'created_at' => $this->created_at?->format('d-m-Y H:i'),
            'updated_at' => $this->updated_at?->format('d-m-Y H:i'),
        ];
    }
}
