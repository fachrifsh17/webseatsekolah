<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuruMapelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            
            'guru' => $this->whenLoaded('guru', function () {
                return [
                    'id'      => $this->guru->id,
                    'nama'    => $this->guru->nama,
                    'nip'     => $this->guru->nip,
                    'display' => $this->guru->nama . ' (' . $this->guru->nip . ')'
                ];
            }),

            'mapel' => $this->whenLoaded('mapel', function () {
                return [
                    'id'         => $this->mapel->id,
                    'nama_mapel' => $this->mapel->nama_mapel,
                    'tipe_mapel' => $this->mapel->tipe_mapel,
                    'jurusan'    => $this->mapel->jurusan?->nama_jurusan,
                    'display'    => $this->mapel->jurusan
                        ? $this->mapel->nama_mapel . ' - ' . $this->mapel->jurusan->nama_jurusan
                        : $this->mapel->nama_mapel
                ];
            }),

            'kelas' => $this->whenLoaded('kelas', function () {
                return [
                    'id'         => $this->kelas->id,
                    'nama_kelas' => $this->kelas->nama_kelas
                ];
            }),

            'tahun_ajaran_id' => $this->tahun_ajaran_id,
            'hari'            => $this->hari,

            'jam_mulai' => $this->whenLoaded('jamMulai', function () {
                return [
                    'id'    => $this->jamMulai->id,
                    'label' => $this->jamMulai->nama_jam ?? null,
                    'mulai' => $this->jamMulai->waktu_mulai ?? null,
                ];
            }),

            'jam_selesai' => $this->whenLoaded('jamSelesai', function () {
                return [
                    'id'     => $this->jamSelesai->id,
                    'label'  => $this->jamSelesai->nama_jam ?? null,
                    'selesai'=> $this->jamSelesai->waktu_selesai ?? null,
                ];
            }),

            'created_at' => $this->created_at?->format('d-m-Y H:i'),
            'updated_at' => $this->updated_at?->format('d-m-Y H:i'),
        ];
    }
}