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
            'id' => $this->id,
            'user' => [
                'id' => $this->user_id,
                'email' => $this->user->email ?? null,
            ],
            'nis' => $this->nis,
            'nama_lengkap' => $this->nama_lengkap,
            'jenis_kelamin' => $this->jenis_kelamin,
            'kelas' => [
                'id' => $this->kelas_id,
                'nama' => $this->kelas->nama_kelas ?? null,
            ],
            'jurusan' => [
                'id' => $this->jurusan_id,
                'nama' => $this->jurusan->nama_jurusan ?? null,
            ],
            'tempat_lahir' => $this->tempat_lahir,
            'tanggal_lahir' => $this->tanggal_lahir,
            'alamat' => $this->alamat,
            'no_hp' => $this->no_hp,
            'foto_url' => $this->foto ? url(Storage::url($this->foto)) : null,
            'total_poin' => ($this->poin_siswa_sum_poin_positif ?? 0) - ($this->poin_siswa_sum_poin_negatif ?? 0),
            'created_at' => $this->created_at->format('d-m-Y H:i'),
        ];
    }
}