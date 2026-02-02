<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PresensiGuruMapelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
<<<<<<< HEAD
            'id' => $this->id,
            'guru_mapel_id' => $this->guru_mapel_id,
            'kelas_id' => $this->kelas_id,
            'mata_pelajaran_id' => $this->mata_pelajaran_id,
            'tanggal' => $this->tanggal,
            'jam_masuk' => $this->jam_masuk,
            'jam_keluar' => $this->jam_keluar,
            'materi' => $this->materi,
            'rincian_presensi_siswa' => collect($this->presensiSiswaDetail ?? [])->map(function ($item) {
                return [
                    'id' => $item->id,
                    'siswa_id' => $item->siswa_id,
                    'nama_siswa' => $item->siswa?->nama_lengkap ?? null,
                    'status' => $item->status,
                    'catatan' => $item->catatan,
                ];
            })->values(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
=======
            'id'                  => $this->id,
            'guru_mapel_id'       => $this->guru_mapel_id,
            'kelas_id'            => $this->kelas_id,
            'mata_pelajaran_id'   => $this->mata_pelajaran_id,
            'tanggal'             => $this->tanggal,
            'jam_masuk'           => $this->jam_masuk,
            'jam_keluar'          => $this->jam_keluar,
            'materi'              => $this->materi,
            'rincian_presensi_siswa' => collect($this->presensiSiswaDetail ?? [])->map(function ($item) {
                return [
                    'id'         => $item->id,
                    'siswa_id'   => $item->siswa_id,
                    'nama_siswa' => $item->siswa?->nama_lengkap ?? null,
                    'status'     => $item->status,
                    'catatan'    => $item->catatan,
                ];
            })->values(),
            'created_at'          => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'          => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
>>>>>>> master
