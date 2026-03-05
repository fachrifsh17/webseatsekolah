<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class PresensiGuruMapelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Jam tetap diambil dari guruMapel karena ini data dinamis jadwal
        $jamMulaiObj = $this->guruMapel?->jamMulai;
        $jamSelesaiObj = $this->guruMapel?->jamSelesai;
        
        $mulai = $jamMulaiObj?->jam_ke ?? $jamMulaiObj?->keterangan;
        $selesai = $jamSelesaiObj?->jam_ke ?? $jamSelesaiObj?->keterangan;

        if (!$mulai) {
            $jamKe = "Jam -";
        } else if (!$selesai || $mulai === $selesai) {
            $jamKe = "Jam {$mulai}";
        } else {
            $jamKe = "Jam {$mulai} - {$selesai}";
        }

        return [
            'id'      => $this->id,
            'tanggal' => $this->tanggal ? Carbon::parse($this->tanggal)->format('Y-m-d') : null,
            
            // Mengambil semester langsung dari relasi 'semester' di model PresensiGuruMapel
            'semester' => [
                'id'              => $this->semester_id,
                'nama'            => $this->semester?->nama ?? '-',
                'tahun_ajaran_id' => $this->semester?->tahun_ajaran_id,
                'tahun'           => $this->semester?->tahun,
            ],
            
            'jadwal' => [
                'id'    => $this->guru_mapel_id,
                // Mengambil data guru, kelas, dan mapel dari relasi LANGSUNG (bukan via guruMapel)
                'guru'  => $this->guru?->nama ?? '-',
                'kelas' => $this->kelas?->nama_kelas ?? '-',
                'mapel' => $this->mapel?->nama_mapel ?? '-',
                'jam_ke'=> $jamKe,
            ],
            
            'materi' => $this->materi ?? '-',
            
            // Rincian kehadiran siswa
            'rincian_siswa' => $this->whenLoaded('presensiDetail', function() {
                return $this->presensiDetail->map(fn($item) => [
                    'id'          => $item->id,
                    'siswa_id'    => $item->siswa_id,
                    'nama_siswa'  => $item->siswa?->nama_lengkap ?? 'Siswa Tidak Ditemukan',
                    'status'      => $item->status, // H, S, I, A
                    'catatan'     => $item->catatan,
                ]);
            }),
            
            'created_at' => $this->created_at?->format('d-m-Y H:i'),
            'updated_at' => $this->updated_at?->format('d-m-Y H:i'),
        ];
    }
}