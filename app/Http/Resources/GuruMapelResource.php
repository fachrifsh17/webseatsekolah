<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class GuruMapelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $mulai = $this->jamMulai->jam_ke ?? null;
        $selesai = $this->jamSelesai->jam_ke ?? null;

        return [
            'id' => $this->id,
            
            'guru' => $this->whenLoaded('guru', fn() => [
                'id'      => $this->guru->id,
                'display' => $this->guru->nama . ' (' . $this->guru->nip . ')'
            ]),

            'mapel' => $this->whenLoaded('mapel', fn() => [
                'id'      => $this->mapel->id,
                'display' => ($this->mapel->jurusan ? $this->mapel->nama_mapel . ' - ' . $this->mapel->jurusan->nama_jurusan : $this->mapel->nama_mapel)
            ]),

            'kelas' => $this->whenLoaded('kelas', fn() => [
                'id'   => $this->kelas->id,
                'nama' => $this->kelas->nama_kelas
            ]),

            'hari' => $this->hari,

            'waktu' => [
                'jam_ke'  => ($mulai && $selesai) ? "Jam $mulai - $selesai" : ($this->jamMulai->nama_jam ?? '-'),
                'mulai'   => $this->jamMulai->waktu_mulai ? Carbon::parse($this->jamMulai->waktu_mulai)->format('H:i') : null,
                'selesai' => $this->jamSelesai->waktu_selesai ? Carbon::parse($this->jamSelesai->waktu_selesai)->format('H:i') : null,
            ],

            'updated_at' => $this->updated_at?->format('d-m-Y H:i'),
        ];
    }
}