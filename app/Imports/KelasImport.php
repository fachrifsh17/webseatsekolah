<?php

namespace App\Imports;

use App\Models\Kelas;
use App\Models\Jurusan;
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
        $line = isset($row['nama_kelas']) ? $row['nama_kelas'] : 'Unknown';

        // 1. Cari Jurusan (Berdasarkan ID atau Nama Jurusan)
        $inputJurusan = trim((string)($row['jurusan'] ?? ''));
        $jurusan = Jurusan::where(function($q) use ($inputJurusan) {
                $q->where('id', $inputJurusan)
                  ->orWhere('nama_jurusan', 'LIKE', '%' . $inputJurusan . '%');
            })
            ->where('is_active', 1)
            ->first();

        if (!$jurusan) {
            $this->messages[] = "Baris [{$line}]: Conflict! Jurusan '{$inputJurusan}' tidak ditemukan.";
            return null;
        }

        $waliKelasId = null;
        $inputWali = trim((string)($row['wali_kelas'] ?? ''));

        if (!empty($inputWali)) {
            // 2. Cari Guru berdasarkan ID atau Nama
            $guru = GuruStaf::where(function($q) use ($inputWali) {
                    $q->where('id', $inputWali) 
                      ->orWhere('nama', 'LIKE', '%' . $inputWali . '%');
                })
                ->where('is_active', 1)
                ->first();
            
            if (!$guru) {
                $this->messages[] = "Baris [{$line}]: Conflict! Guru ID/Nama '{$inputWali}' tidak ditemukan.";
                return null;
            }

            // 3. Cek apakah sudah jadi Wali Kelas (Tanpa filter Tahun Ajaran sesuai permintaan)
            $sudahJadiWali = Kelas::where('wali_kelas_id', $guru->id)->exists();

            if ($sudahJadiWali) {
                $this->messages[] = "Baris [{$line}]: Conflict! Guru '{$guru->nama}' sudah menjabat di kelas lain.";
                return null;
            }

            $waliKelasId = $guru->id;
        }

        // 4. Cek Duplikasi Nama Kelas
        $namaKelas = trim((string)($row['nama_kelas'] ?? ''));
        $isDuplicate = Kelas::where('nama_kelas', $namaKelas)->exists();

        if ($isDuplicate) {
            $this->messages[] = "Baris [{$line}]: Skipped! Kelas '{$namaKelas}' sudah terdaftar.";
            return null;
        }

        // 5. Simpan dengan Auto-Numbering ID (K001...)
        try {
            return DB::transaction(function () use ($namaKelas, $jurusan, $waliKelasId) {
                $lastKelas = Kelas::where('id', 'like', 'K%')
                    ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                    ->lockForUpdate()
                    ->first();

                $lastNumber = $lastKelas ? (int) substr($lastKelas->id, 1) : 0;
                $newId = 'K' . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);

                return new Kelas([
                    'id'              => $newId,
                    'nama_kelas'      => $namaKelas,
                    'jurusan_id'      => $jurusan->id,
                    'wali_kelas_id'   => $waliKelasId,
                    'is_active'       => 1,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Import Kelas Error: ' . $e->getMessage());
            $this->messages[] = "Baris [{$line}]: Gagal simpan ke database.";
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