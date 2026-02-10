<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class PresensiGuruMapelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // PENTING: Gunakan nama relasi sesuai yang ada di Model PresensiGuruMapel
        $jamMulaiObj = $this->jamMasukDetail ?? $this->guruMapel?->jamMulai;
        $jamSelesaiObj = $this->jamKeluarDetail ?? $this->guruMapel?->jamSelesai;
        
        // Mengecek jam_ke atau keterangan agar tidak null
        $mulai = $jamMulaiObj?->jam_ke ?? $jamMulaiObj?->keterangan ?? '-';
        $selesai = $jamSelesaiObj?->jam_ke ?? $jamSelesaiObj?->keterangan ?? '-';

        // Logika tampilan: jika jam sama atau jam selesai tidak ada
        $jamKe = ($mulai === $selesai || $selesai === '-') 
            ? "Jam {$mulai}" 
            : "Jam {$mulai} - {$selesai}";

        return [
            'id'      => $this->id,
            'tanggal' => $this->tanggal ? Carbon::parse($this->tanggal)->format('Y-m-d') : null,
            'tahun_ajaran' => [
                'id'       => $this->tahun_ajaran_id,
                'nama'     => $this->tahunAjaran?->nama,
                'semester' => $this->tahunAjaran?->semester,
            ],
            'jadwal' => [
                'id'    => $this->guru_mapel_id,
                'guru'  => $this->guruMapel?->guru?->nama_selengkapnya ?? ($this->guruMapel?->guru?->nama ?? '-'),
                'kelas' => $this->kelas?->nama_kelas ?? ($this->guruMapel?->kelas?->nama_kelas ?? '-'),
                'mapel' => $this->guruMapel?->mapel?->nama_mapel ?? '-',
                'jam_ke'=> $jamKe,
            ],
            'materi' => $this->materi ?? '-',
            'rincian_siswa' => $this->whenLoaded('presensiSiswaDetail', function() {
                return collect($this->presensiSiswaDetail)->map(fn($item) => [
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