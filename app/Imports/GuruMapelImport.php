<?php

namespace App\Imports;

use App\Models\GuruMapel;
use App\Models\GuruStaf;
use App\Models\MataPelajaran;
use App\Models\Kelas;
use App\Models\Semester;
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

        $semesterAktif = Semester::where('is_active', 1)->first();
        
        if (!$semesterAktif) {
            $this->importMessages[] = "Baris {$this->rows}: Tidak ada semester yang diatur sebagai 'aktif'.";
            return null;
        }

        $hariInput = trim((string)($row['hari'] ?? ''));
        $inputGuru = trim((string)($row['guru'] ?? ''));
        $inputKelas = trim((string)($row['kelas'] ?? ''));
        $inputMapel = trim((string)($row['mapel'] ?? ''));

        $guru = GuruStaf::where(function($q) use ($inputGuru) {
                $q->where('id', $inputGuru)
                  ->orWhere('nama', 'LIKE', '%' . $inputGuru . '%');
            })
            ->where('is_active', 1)
            ->first();

        $mapel = MataPelajaran::where('nama_mapel', 'LIKE', '%' . $inputMapel . '%')
            ->where('is_active', 1)
            ->first();

        $kelas = Kelas::where('nama_kelas', 'LIKE', '%' . $inputKelas . '%')
            ->where('is_active', 1)
            ->first();
        
        $jamMulai = JamSekolah::where('jam_ke', $row['jam_ke_mulai'] ?? '')
            ->where('hari', $hariInput)
            ->first();
            
        $jamSelesai = JamSekolah::where('jam_ke', $row['jam_ke_selesai'] ?? '')
            ->where('hari', $hariInput)
            ->first();

        if (!$guru) {
            $this->importMessages[] = "Baris {$this->rows}: Conflict! Guru '{$inputGuru}' tidak ditemukan atau non-aktif.";
            return null;
        }
        if (!$mapel) {
            $this->importMessages[] = "Baris {$this->rows}: Conflict! Mapel '{$inputMapel}' tidak ditemukan atau non-aktif.";
            return null;
        }
        if (!$kelas) {
            $this->importMessages[] = "Baris {$this->rows}: Conflict! Kelas '{$inputKelas}' tidak ditemukan atau non-aktif.";
            return null;
        }
        if (!$jamMulai || !$jamSelesai) {
            $this->importMessages[] = "Baris {$this->rows}: Conflict! Jam ke-{$row['jam_ke_mulai']} s/d {$row['jam_ke_selesai']} tidak ada di hari {$hariInput}.";
            return null;
        }

        if ($jamSelesai->waktu_selesai <= $jamMulai->waktu_mulai) {
            $this->importMessages[] = "Baris {$this->rows}: Logika salah! Waktu selesai harus setelah waktu mulai.";
            return null;
        }

        $bentrok = GuruMapel::where('hari', $hariInput)
            ->where('semester_id', $semesterAktif->id)
            ->where(function ($q) use ($jamMulai, $jamSelesai) {
                $q->where(function($query) use ($jamMulai, $jamSelesai) {
                    $query->whereHas('jamMulai', function ($sub) use ($jamSelesai) {
                        $sub->where('waktu_mulai', '<', $jamSelesai->waktu_selesai);
                    })->whereHas('jamSelesai', function ($sub) use ($jamMulai) {
                        $sub->where('waktu_selesai', '>', $jamMulai->waktu_mulai);
                    });
                });
            })
            ->where(function ($q) use ($guru, $kelas) {
                $q->where('guru_staf_id', $guru->id)
                  ->orWhere('kelas_id', $kelas->id);
            })
            ->first();

        if ($bentrok) {
            $subjek = $bentrok->guru_staf_id == $guru->id ? "Guru '{$guru->nama}'" : "Kelas '{$kelas->nama_kelas}'";
            $this->importMessages[] = "Baris {$this->rows}: Conflict! {$subjek} sudah ada jadwal lain di jam ini pada semester aktif.";
            return null;
        }

        return new GuruMapel([
            'guru_staf_id'      => $guru->id,
            'mata_pelajaran_id' => $mapel->id,
            'kelas_id'          => $kelas->id,
            'semester_id'       => $semesterAktif->id,
            'hari'              => $hariInput,
            'jam_mulai_id'      => $jamMulai->id,
            'jam_selesai_id'    => $jamSelesai->id,
            'is_active'         => 1, // --- PERUBAHAN DI SINI ---
        ]);
    }

    public function getMessages(): array
    {
        return $this->importMessages;
    }
}