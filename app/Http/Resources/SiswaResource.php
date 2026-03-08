<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiswaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Mencari riwayat kelas yang sedang aktif
        $riwayatAktif = $this->relationLoaded('riwayatKelas') 
            ? $this->riwayatKelas->firstWhere('is_active', 1) 
            : null;
            
        $dataKelas = $riwayatAktif ? $riwayatAktif->kelas : null;

        return [
            'id'            => $this->id,
            'user_id'       => $this->user_id,
            'nis'           => $this->nis,
            'nisn'          => $this->nisn,
            'nama_lengkap'  => $this->nama_lengkap,
            'tempat_lahir'  => $this->tempat_lahir,
            'tanggal_lahir' => $this->tanggal_lahir?->toDateString(),
            'jenis_kelamin' => $this->jenis_kelamin,

            'kelas' => $dataKelas ? [
                'id'   => $dataKelas->id,
                'nama' => $dataKelas->nama_kelas,
                
                'jurusan' => $this->when($dataKelas->relationLoaded('jurusan') && $dataKelas->jurusan, function() use ($dataKelas) {
                    return [
                        'id'   => $dataKelas->jurusan->id,
                        'nama' => $dataKelas->jurusan->nama_jurusan,
                    ];
                }),
            ] : null,

            'orangtua' => $this->relationLoaded('orangtua') ? $this->orangtua->map(function ($o) {
                return [
                    'id'           => $o->id,
                    'nama_lengkap' => $o->nama_lengkap ?? $o->nama ?? null,
                    'telepon'      => $o->telepon ?? $o->no_telp ?? null,
                    'hubungan'     => $o->pivot?->hubungan ?? $o->hubungan ?? null,
                ];
            })->values() : [],

            'foto'          => $this->foto,
            
            'foto_url'      => $this->foto 
                                ? asset('uploads/' . str_replace('uploads/', '', $this->foto)) 
                                : asset('images/default-avatar.png'),
                               
            'no_telp_siswa' => $this->no_telp_siswa,
            'alamat'        => $this->alamat,
            'is_active'     => $this->is_active !== null ? (int) $this->is_active : null,

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}