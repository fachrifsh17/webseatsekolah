<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PresensiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Kondisi jika dipanggil dari Siswa Wali (Format Flat)
        if ($request->is('*siswa-wali*')) {
            return [
                'id'  => $this->id,
                'siswa_id'     => $this->siswa_id,
                'nama_lengkap' => $this->siswa->nama_lengkap ?? null,
                'kelas'        => $this->siswa->kelas->nama_kelas ?? '-',
                'status'       => $this->status,
                'keterangan'   => $this->keterangan,
                'tanggal'      => $this->tanggal,
            ];
        }

        return [
            'id'      => $this->id,
            'siswa'   => [
                'id'    => $this->siswa_id,
                'nama'  => $this->siswa->nama_lengkap ?? null,
                'kelas' => $this->siswa->kelas->nama_kelas ?? null,
            ],
            'guru_staf' => [
                'id'   => $this->guru_staf_id,
                // Ini akan menampilkan nama Wali Kelas/Guru yang mengabsen
                'nama' => $this->guruStaf->nama ?? null,
            ],
            'tahun_ajaran' => [
                'id'       => $this->tahun_ajaran_id,
                'semester' => $this->tahunAjaran->semester ?? null,
                'tahun'    => $this->tahunAjaran->tahun_ajaran ?? null,
            ],
            'tanggal'    => $this->tanggal,
            'status'     => $this->status,
            'keterangan' => $this->keterangan,
        ];
    }
}