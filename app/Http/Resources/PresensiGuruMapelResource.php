<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class PresensiGuruMapelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // 1. Ambil objek jam (prioritas dari tabel presensi, fallback ke tabel jadwal)
        $jamMulaiObj = $this->jamMasukDetail ?? $this->guruMapel?->jamMulai;
        $jamSelesaiObj = $this->jamKeluarDetail ?? $this->guruMapel?->jamSelesai;
        
        // 2. Ambil nilai mentahnya (null jika tidak ada)
        $mulai = $jamMulaiObj?->jam_ke ?? $jamMulaiObj?->keterangan;
        $selesai = $jamSelesaiObj?->jam_ke ?? $jamSelesaiObj?->keterangan;

        // 3. Logika penentuan string jam_ke
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
            // --- PERUBAHAN DI SINI ---
            'semester' => [
                'id'              => $this->semester_id,
                'nama'            => $this->semester?->nama,
                'tahun_ajaran_id' => $this->semester?->tahun_ajaran_id,
            ],
            'jadwal' => [
                'id'    => $this->guru_mapel_id,
                'guru'  => $this->guruMapel?->guru?->nama_selengkapnya ?? ($this->guruMapel?->guru?->nama ?? '-'),
                'kelas' => $this->kelas?->nama_kelas ?? ($this->guruMapel?->kelas?->nama_kelas ?? '-'),
                'mapel' => $this->mapel?->nama_mapel ?? ($this->guruMapel?->mapel?->nama_mapel ?? '-'),
                'jam_ke'=> $jamKe,
            ],
            'materi' => $this->materi ?? '-',
            'rincian_siswa' => $this->whenLoaded('getBySiswaDetil', function() {
                return collect($this->getBySiswaDetil)->map(fn($item) => [
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