<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class PresensiGuruMapelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'      => $this->id,
            'tanggal' => $this->tanggal,

            'jadwal' => $this->whenLoaded('guruMapel', function() {
                // Gunakan optional() agar jika relasi kosong tidak menyebabkan crash
                $jamMulai = $this->guruMapel->jamMulai;
                $jamSelesai = $this->guruMapel->jamSelesai;
                
                return [
                    'id'      => $this->guruMapel->id,
                    'guru'    => $this->guruMapel->guru?->nama,
                    'kelas'   => $this->guruMapel->kelas?->nama_kelas,
                    'mapel'   => $this->guruMapel->mapel?->nama_mapel,
                    'jam_ke'  => ($jamMulai?->jam_ke && $jamSelesai?->jam_ke) 
                                 ? "Jam {$jamMulai->jam_ke} - {$jamSelesai->jam_ke}" : '-',
                ];
            }),

            'materi' => $this->materi ?? '-',

            'rincian_siswa' => collect($this->presensiSiswaDetail ?? [])->map(fn($item) => [
                'id'         => $item->id,
                'nama_siswa' => $item->siswa?->nama_lengkap ?? 'Siswa Tidak Ditemukan',
                'status'     => $item->status,
                'catatan'    => $item->catatan,
            ])->values(),

            'updated_at' => $this->updated_at?->format('d-m-Y H:i'),
        ];
    }

    /**
     * Fungsi pembantu untuk mengecek apakah string adalah waktu yang valid untuk diparse Carbon
     */
    private function isValidTime($time)
    {
        if (!$time) return false;
        // Jika string mengandung "JM" (seperti JM002), berarti itu ID, jangan diparse Carbon
        return !str_contains($time, 'JM');
    }
}