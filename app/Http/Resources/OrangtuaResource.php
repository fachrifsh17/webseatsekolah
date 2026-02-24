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
                $res = [
                    'id'       => $a->id,
                    'nis'      => $a->nis,
                    'nama'     => $a->nama_lengkap,
                    'hubungan' => $a->pivot?->hubungan,
                    'kelas'    => null, // Default null jika tidak ada riwayat
                ];

                // PERBAIKAN: Menggunakan 'riwayatKelas' sesuai yang di-load di Controller
                $riwayat = $a->relationLoaded('riwayatKelas') ? $a->riwayatKelas->first() : null;

                if ($riwayat && $riwayat->kelas) {
                    $res['kelas'] = [
                        'id'   => $riwayat->kelas->id,
                        'nama' => $riwayat->kelas->nama_kelas,
                        'jurusan' => ($riwayat->kelas->relationLoaded('jurusan') && $riwayat->kelas->jurusan) ? [
                            'id'   => $riwayat->kelas->jurusan->id,
                            'nama' => $riwayat->kelas->jurusan->nama_jurusan,
                        ] : null,
                    ];
                }

                return $res;
            })->values() : [],

            'created_at'   => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'   => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}