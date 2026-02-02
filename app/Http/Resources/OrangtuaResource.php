<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrangtuaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'user'         => $this->relationLoaded('user') && $this->user ? [
                'id'       => $this->user->id,
                'username' => $this->user->username,
            ] : [
                'id'       => $this->user_id,
                'username' => null,
            ],
            'nama_lengkap' => $this->nama_lengkap,
            'telepon'      => $this->telepon,
            'is_active'    => $this->is_active !== null ? (int) $this->is_active : null,

            'anak' => $this->relationLoaded('anak') ? $this->anak->map(function ($a) {
                return [
                    'id'       => $a->id,
                    'nis'      => $a->nis,
                    'nama'     => $a->nama_lengkap,
                    'hubungan' => $a->pivot?->hubungan,
                    'kelas'    => $a->relationLoaded('kelas') && $a->kelas ? [
                        'id'   => $a->kelas->id,
                        'nama' => $a->kelas->nama_kelas,
                        'jurusan' => $a->kelas->relationLoaded('jurusan') && $a->kelas->jurusan ? [
                            'id'   => $a->kelas->jurusan->id,
                            'nama' => $a->kelas->jurusan->nama_jurusan,
                        ] : null,
                    ] : null,
                ];
            })->values() : [],

            'created_at'   => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'   => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
