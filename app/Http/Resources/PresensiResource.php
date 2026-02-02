<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
<<<<<<< HEAD
=======
use Carbon\Carbon;
>>>>>>> master

class PresensiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
<<<<<<< HEAD
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
=======
        $isWali = $request->is('*siswa-wali*');

        return [
            'id' => $this->id,
            'tanggal' => $this->tanggal ? Carbon::parse($this->tanggal)->format('Y-m-d') : null,
            'status' => $this->status,
            'keterangan' => $this->keterangan,
            
            $this->mergeWhen($isWali, [
                'siswa_id' => (string) $this->siswa_id,
                'nama_lengkap' => $this->siswa?->nama_lengkap,
                'kelas' => $this->siswa?->kelas?->nama_kelas,
            ]),

            $this->mergeWhen(!$isWali, [
                'siswa' => [
                    'id' => (string) $this->siswa_id,
                    'nama' => $this->siswa?->nama_lengkap,
                    'kelas' => $this->siswa?->kelas?->nama_kelas,
                ],
                'guru' => $this->guruStaf?->nama,
                'tahun_ajaran' => [
                    'tahun' => $this->tahunAjaran?->nama,
                    'semester' => $this->tahunAjaran?->semester,
                ],
            ]),
>>>>>>> master
        ];
    }
}