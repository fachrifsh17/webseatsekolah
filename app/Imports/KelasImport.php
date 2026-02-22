<?php

namespace App\Imports;

use App\Models\Kelas;
use App\Models\Jurusan;
use App\Models\TahunAjaran;
use App\Models\GuruStaf;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KelasImport implements ToModel, WithHeadingRow, WithValidation
{
    private array $messages = [];

    public function model(array $row)
    {
        // 1. Cek Tahun Ajaran yang Aktif
        $tahunAktif = TahunAjaran::where('is_active', 1)->first();
        
        if (!$tahunAktif) {
            $this->messages[] = "Baris skipped: Tidak ada Tahun Ajaran aktif di sistem.";
            return null;
        }

        // 2. Cek Jurusan yang Aktif (Tambahan is_active)
        $jurusan = Jurusan::where('nama_jurusan', trim($row['jurusan']))
            ->where('is_active', 1)
            ->first();

        if (!$jurusan) {
            $this->messages[] = "Baris skipped: Jurusan '" . ($row['jurusan'] ?? 'Kosong') . "' tidak ditemukan atau tidak aktif.";
            return null;
        }

        $waliKelasId = null;
        if (!empty($row['wali_kelas'])) {
            // 3. Cek Guru yang Aktif (Tambahan is_active)
            $guru = GuruStaf::where('nama', trim($row['wali_kelas']))
                ->where('is_active', 1)
                ->first();
            
            if ($guru) {
                // Cek apakah guru sudah jadi wali kelas di tahun ajaran aktif ini
                $sudahJadiWali = Kelas::where('wali_kelas_id', $guru->id)
                    ->where('tahun_ajaran_id', $tahunAktif->id)
                    ->exists();

                if ($sudahJadiWali) {
                    $this->messages[] = "Baris skipped: Guru '{$row['wali_kelas']}' sudah terdaftar sebagai wali kelas di kelas lain pada periode ini.";
                    return null;
                }

                $waliKelasId = $guru->id;
            } else {
                $this->messages[] = "Baris skipped: Guru '{$row['wali_kelas']}' tidak ditemukan atau statusnya tidak aktif.";
                return null;
            }
        }

        // 4. Cek duplikasi nama kelas di tahun ajaran yang sama
        $isDuplicate = Kelas::where('nama_kelas', trim($row['nama_kelas']))
            ->where('tahun_ajaran_id', $tahunAktif->id)
            ->exists();

        if ($isDuplicate) {
            $this->messages[] = "Baris skipped: Kelas '{$row['nama_kelas']}' sudah ada di tahun ajaran ini.";
            return null;
        }

        try {
            return DB::transaction(function () use ($row, $tahunAktif, $jurusan, $waliKelasId) {
                $lastKelas = Kelas::where('id', 'like', 'K%')
                    ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                    ->lockForUpdate()
                    ->first();

                $lastNumber = $lastKelas ? (int) substr($lastKelas->id, 1) : 0;
                $newId = 'K' . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);

                return new Kelas([
                    'id'              => $newId,
                    'nama_kelas'      => trim($row['nama_kelas']),
                    'jurusan_id'      => $jurusan->id,
                    'wali_kelas_id'   => $waliKelasId,
                    'tahun_ajaran_id' => $tahunAktif->id,
                    'is_active'       => 1,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Import Kelas Error: ' . $e->getMessage());
            $this->messages[] = "Baris error: Gagal menyimpan kelas '{$row['nama_kelas']}'.";
            return null;
        }
    }

    public function rules(): array
    {
        return [
            'nama_kelas' => 'required',
            'jurusan'    => 'required',
            'wali_kelas' => 'nullable',
        ];
    }

    public function getMessages(): array
    {
        return $this->messages;
    }
}