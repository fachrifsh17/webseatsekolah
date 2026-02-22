<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SiswaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'user_id'       => $this->user_id,
            'nis'           => $this->nis,
            'nisn'          => $this->nisn,
            'nama_lengkap'  => $this->nama_lengkap,
            'tempat_lahir'  => $this->tempat_lahir,
            'tanggal_lahir' => $this->tanggal_lahir?->toDateString(),
            'jenis_kelamin' => $this->jenis_kelamin,

            'kelas' => $this->relationLoaded('kelas') && $this->kelas ? [
                'id'      => $this->kelas->id,
                'nama'    => $this->kelas->nama_kelas,
                'jurusan' => $this->kelas->relationLoaded('jurusan') && $this->kelas->jurusan ? [
                    'id'   => $this->kelas->jurusan->id,
                    'nama' => $this->kelas->jurusan->nama_jurusan,
                ] : null,
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
            // Menggunakan url() untuk memastikan link menjadi absolut (http://...)
            'foto_url'      => $this->foto ? url(Storage::url($this->foto)) : null,
            'no_telp_siswa' => $this->no_telp_siswa,
            'alamat'        => $this->alamat,
            'is_active'     => $this->is_active !== null ? (int) $this->is_active : null,

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}