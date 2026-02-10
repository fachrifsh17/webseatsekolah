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

        $guru = GuruStaf::where('nama', 'LIKE', '%' . $row['guru'] . '%')
            ->orWhere('nip', $row['guru'])
            ->first();

        $mapel = MataPelajaran::where('nama_mapel', 'LIKE', '%' . $row['mapel'] . '%')->first();
        $kelas = Kelas::where('nama_kelas', 'LIKE', '%' . $row['kelas'] . '%')->first();
        $jamMulai = JamSekolah::where('jam_ke', $row['jam_ke_mulai'])->first();
        $jamSelesai = JamSekolah::where('jam_ke', $row['jam_ke_selesai'])->first();
        $tahunAktif = TahunAjaran::where('is_active', 1)->first();

        // 1. Validasi Keberadaan Data (Master Data)
        if (!$tahunAktif) {
            $this->importMessages[] = "Baris {$this->rows}: Tidak ada Tahun Ajaran yang aktif.";
            return null;
        }
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
        if (!$jamMulai || !$jamSelesai) {
            $this->importMessages[] = "Baris {$this->rows}: Jam ke-{$row['jam_ke_mulai']} atau ke-{$row['jam_ke_selesai']} tidak ditemukan.";
            return null;
        }

        // 2. Cek Bentrok Jadwal
        $isBentrok = GuruMapel::where([
            'guru_staf_id'    => $guru->id,
            'tahun_ajaran_id' => $tahunAktif->id,
            'hari'            => trim($row['hari']),
            'jam_mulai_id'    => $jamMulai->id,
        ])->exists();

        if ($isBentrok) {
            $this->importMessages[] = "Baris {$this->rows}: Jadwal guru '{$guru->nama}' hari {$row['hari']} jam ke-{$row['jam_ke_mulai']} sudah terdaftar (Bentrok).";
            return null;
        }

        return new GuruMapel([
            'guru_staf_id'      => $guru->id,
            'mata_pelajaran_id' => $mapel->id,
            'kelas_id'          => $kelas->id,
            'tahun_ajaran_id'   => $tahunAktif->id,
            'hari'              => trim($row['hari']),
            'jam_mulai_id'      => $jamMulai->id,
            'jam_selesai_id'    => $jamSelesai->id,
        ]);
    }

    public function getMessages(): array
    {
        return $this->importMessages;
    }
}