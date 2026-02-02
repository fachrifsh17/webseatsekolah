<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class PresensiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
                    'tahun' => $this->tahunAjaran?->nama ?? $this->tahunAjaran?->tahun_ajaran,
                    'semester' => $this->tahunAjaran?->semester,
                ],
            ]),
        ];
    }
}