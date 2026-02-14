<?php

namespace App\Imports;

use App\Models\GuruMapel;
use App\Models\GuruStaf;
use App\Models\MataPelajaran;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Models\JamSekolah;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class GuruMapelImport implements ToModel, WithHeadingRow, SkipsEmptyRows
{
    public array $importMessages = [];
    private int $rows = 0;

    public function model(array $row)
    {
        $this->rows++;

        $tahunAktif = TahunAjaran::where('is_active', 1)->first();
        if (!$tahunAktif) {
            $this->importMessages[] = "Baris {$this->rows}: Tidak ada Tahun Ajaran yang aktif.";
            return null;
        }

        $hariInput = trim($row['hari']);
        
        $guru = GuruStaf::where('nama', 'LIKE', '%' . $row['guru'] . '%')
            ->orWhere('nip', $row['guru'])
            ->first();

        $mapel = MataPelajaran::where('nama_mapel', 'LIKE', '%' . $row['mapel'] . '%')->first();
        $kelas = Kelas::where('nama_kelas', 'LIKE', '%' . $row['kelas'] . '%')->first();
        
        // Pencarian jam sekarang dikunci berdasarkan HARI
        $jamMulai = JamSekolah::where('jam_ke', $row['jam_ke_mulai'])
            ->where('hari', $hariInput)
            ->first();
            
        $jamSelesai = JamSekolah::where('jam_ke', $row['jam_ke_selesai'])
            ->where('hari', $hariInput)
            ->first();

        if (!$guru) {
            $this->importMessages[] = "Baris {$this->rows}: Guru '{$row['guru']}' tidak ditemukan.";
            return null;
        }
        if (!$mapel) {
            $this->importMessages[] = "Baris {$this->rows}: Mapel '{$row['mapel']}' tidak ditemukan.";
            return null;
        }
        if (!$kelas) {
            $this->importMessages[] = "Baris {$this->rows}: Kelas '{$row['kelas']}' tidak ditemukan.";
            return null;
        }

        // Cek apakah jam tersebut memang ada di hari tersebut
        if (!$jamMulai || !$jamSelesai) {
            $this->importMessages[] = "Baris {$this->rows}: Jam ke-{$row['jam_ke_mulai']} atau ke-{$row['jam_ke_selesai']} tidak tersedia pada hari {$hariInput}.";
            return null;
        }

        // Cek Logika Waktu (Selesai harus > Mulai)
        if ($jamSelesai->waktu_selesai <= $jamMulai->waktu_mulai) {
            $this->importMessages[] = "Baris {$this->rows}: Logika waktu salah, jam selesai harus lebih besar dari jam mulai.";
            return null;
        }

        $bentrok = GuruMapel::where('hari', $hariInput)
            ->where('tahun_ajaran_id', $tahunAktif->id)
            ->where(function ($q) use ($jamMulai, $jamSelesai) {
                $q->whereHas('jamMulai', function ($query) use ($jamSelesai) {
                    $query->where('waktu_mulai', '<', $jamSelesai->waktu_selesai);
                })->whereHas('jamSelesai', function ($query) use ($jamMulai) {
                    $query->where('waktu_selesai', '>', $jamMulai->waktu_mulai);
                });
            })
            ->where(function ($q) use ($guru, $kelas) {
                $q->where('guru_staf_id', $guru->id)
                  ->orWhere('kelas_id', $kelas->id);
            })
            ->first();

        if ($bentrok) {
            $type = $bentrok->guru_staf_id == $guru->id ? "Guru '{$guru->nama}'" : "Kelas '{$kelas->nama_kelas}'";
            $this->importMessages[] = "Baris {$this->rows}: {$type} sudah memiliki jadwal lain di jam tersebut (Bentrok).";
            return null;
        }

        $exists = GuruMapel::where([
            'guru_staf_id'      => $guru->id,
            'mata_pelajaran_id' => $mapel->id,
            'kelas_id'          => $kelas->id,
            'tahun_ajaran_id'   => $tahunAktif->id,
        ])->exists();

        if ($exists) {
            $this->importMessages[] = "Baris {$this->rows}: Data penugasan ini sudah terdaftar sebelumnya.";
            return null;
        }

        return new GuruMapel([
            'guru_staf_id'      => $guru->id,
            'mata_pelajaran_id' => $mapel->id,
            'kelas_id'          => $kelas->id,
            'tahun_ajaran_id'   => $tahunAktif->id,
            'hari'              => $hariInput,
            'jam_mulai_id'      => $jamMulai->id,
            'jam_selesai_id'    => $jamSelesai->id,
        ]);
    }

    public function getMessages(): array
    {
        return $this->importMessages;
    }
}