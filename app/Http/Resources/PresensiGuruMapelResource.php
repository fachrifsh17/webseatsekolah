<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class PresensiGuruMapelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Ambil data jam dari relasi guruMapel saja, 
        // karena presensi_guru_mapel tidak menyimpan jam lagi
        $jamMulaiObj = $this->guruMapel?->jamMulai;
        $jamSelesaiObj = $this->guruMapel?->jamSelesai;
        
        // Ambil nilai mentahnya (null jika tidak ada)
        $mulai = $jamMulaiObj?->jam_ke ?? $jamMulaiObj?->keterangan;
        $selesai = $jamSelesaiObj?->jam_ke ?? $jamSelesaiObj?->keterangan;

        // Logika penentuan string jam_ke
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
            
            // Ambil semester dari relasi guruMapel
            'semester' => [
                'id'              => $this->guruMapel?->semester_id,
                'nama'            => $this->guruMapel?->semester?->nama,
                'tahun_ajaran_id' => $this->guruMapel?->semester?->tahun_ajaran_id,
            ],
            
            'jadwal' => [
                'id'    => $this->guru_mapel_id,
                'guru'  => $this->guruMapel?->guru?->nama_selengkapnya ?? ($this->guruMapel?->guru?->nama ?? '-'),
                // Ambil kelas dan mapel langsung dari relasi guruMapel
                'kelas' => $this->guruMapel?->kelas?->nama_kelas ?? '-',
                'mapel' => $this->guruMapel?->mapel?->nama_mapel ?? '-',
                'jam_ke'=> $jamKe,
            ],
            
            'materi' => $this->materi ?? '-',
            
            'rincian_siswa' => $this->whenLoaded('presensiDetail', function() {
                return collect($this->presensiDetail)->map(fn($item) => [
                    'id'         => $item->id,
                    'nama_siswa' => $item->siswa?->nama_lengkap ?? 'Siswa Tidak Ditemukan',
                    'status'     => $item->status,
                    'catatan'    => $item->catatan,
                ])->values();
            }),
            
            'updated_at' => $this->updated_at?->format('d-m-Y H:i'),
        ];
    }
}