<?php

namespace App\Imports;

use App\Models\Kelas;
use App\Models\Jurusan;
// --- PENYESUAIAN IMPORT ---
use App\Models\Tingkatan; 
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

        // 2. Cari Tingkatan berdasarkan Nama (misal: "X", "XI", "XII")
        $inputTingkatan = trim((string)($row['tingkatan'] ?? ''));
        $tingkatan = Tingkatan::where('nama_tingkatan', $inputTingkatan)->first();

        if (!$tingkatan) {
            $this->messages[] = "Baris [{$line}]: Conflict! Tingkatan '{$inputTingkatan}' tidak ditemukan.";
            return null;
        }

        // 3. Cek Duplikasi Nama Kelas
        $namaKelas = trim((string)($row['nama_kelas'] ?? ''));
        $isDuplicate = Kelas::where('nama_kelas', $namaKelas)->exists();

        if ($isDuplicate) {
            $this->messages[] = "Baris [{$line}]: Skipped! Kelas '{$namaKelas}' sudah terdaftar.";
            return null;
        }

        // 4. Simpan dengan Auto-Numbering ID (K001...)
        try {
            return DB::transaction(function () use ($namaKelas, $jurusan, $tingkatan) {
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
                    'tingkatan_id'    => $tingkatan->id,
                    'is_active'       => 1,
                    // wali_kelas_id dihapus
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
            'tingkatan'  => 'required',
            // wali_kelas dihapus dari rules
        ];
    }

    public function getMessages(): array
    {
        return $this->messages;
    }
}