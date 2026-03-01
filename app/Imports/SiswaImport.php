<?php

namespace App\Imports;

use App\Models\Siswa;
use App\Models\User;
use App\Models\Kelas;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class SiswaImport implements ToModel, WithHeadingRow
{
    public array $importMessages = [];
    private int $rows = 0;

    public function model(array $row)
    {
        $this->rows++;

        // 1. Validasi kolom wajib
        if (empty($row['nis']) || empty($row['nama_lengkap'])) {
            $this->importMessages[] = "Baris {$this->rows}: Dilewati karena NIS atau Nama Lengkap kosong.";
            return null;
        }

        // 2. Cek Duplikat NIS/NISN
        $existingSiswa = Siswa::where('nis', $row['nis'])
            ->when(!empty($row['nisn']), function ($q) use ($row) {
                return $q->orWhere('nisn', $row['nisn']);
            })
            ->first();

        if ($existingSiswa) {
            $this->importMessages[] = "Baris {$this->rows}: Siswa dengan NIS/NISN tersebut sudah terdaftar.";
            return null;
        }

        // 3. Ambil Semester Aktif saja (Tahun Ajaran dihapus sesuai permintaan)
        $semesterAktif = DB::table('semesters')->where('is_active', 1)->first();
        
        if (!$semesterAktif) {
            $this->importMessages[] = "Baris {$this->rows}: Gagal Import. Pastikan Semester aktif sudah diatur.";
            return null;
        }

        // 4. Cari Kelas Berdasarkan Nama dan Status Aktif
        $namaKelasInput = isset($row['kelas']) ? trim($row['kelas']) : null;
        $kelasId = null;

        if (!empty($namaKelasInput)) {
            $kelas = Kelas::where('nama_kelas', $namaKelasInput)
                ->where('is_active', 1)
                ->first();

            if ($kelas) {
                $kelasId = $kelas->id;
            } else {
                $this->importMessages[] = "Baris {$this->rows}: Kelas '{$namaKelasInput}' tidak ditemukan atau tidak aktif.";
                return null; 
            }
        } else {
            $this->importMessages[] = "Baris {$this->rows}: Kolom kelas kosong.";
            return null;
        }

        // 5. Proses Simpan Data
        return DB::transaction(function () use ($row, $kelasId, $semesterAktif) {
            // Generate User ID
            $lastUser = User::where('id', 'like', 'U%')
                ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                ->lockForUpdate()
                ->first();
                
            $lastUserId = $lastUser ? (int) substr($lastUser->id, 1) : 0;
            $newUserId = 'U' . str_pad($lastUserId + 1, 3, '0', STR_PAD_LEFT);

            User::create([
                'id'           => $newUserId,
                'username'     => $row['nis'], 
                'password'     => Hash::make($row['nis']),
                'current_role' => 'Siswa',
                'is_active'    => 1,
            ]);

            DB::table('user_roles')->insert([
                'user_id'    => $newUserId,
                'role_id'    => 'R003', 
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Generate Siswa ID
            $lastSiswa = Siswa::where('id', 'like', 'S%')
                ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                ->lockForUpdate()
                ->first();

            $lastSiswaId = $lastSiswa ? (int) substr($lastSiswa->id, 1) : 0;
            $newSiswaId = 'S' . str_pad($lastSiswaId + 1, 3, '0', STR_PAD_LEFT);

            // Simpan data siswa
            $siswa = Siswa::create([
                'id'            => $newSiswaId,
                'user_id'       => $newUserId,
                'nis'           => $row['nis'],
                'nisn'          => $row['nisn'] ?? null,
                'nama_lengkap'  => $row['nama_lengkap'],
                'tempat_lahir'  => $row['tempat_lahir'] ?? null,
                'tanggal_lahir' => $row['tanggal_lahir'] ?? null,
                'jenis_kelamin' => $row['jenis_kelamin'] ?? null,
                'is_active'     => 1,
                'no_telp_siswa' => $row['no_telp_siswa'] ?? null,
                'alamat'        => $row['alamat'] ?? null,
            ]);

            // --- INPUT PIVOT KELAS ---
            if ($kelasId) {
                DB::table('siswa_kelas')->insert([
                    'siswa_id'    => $newSiswaId,
                    'kelas_id'    => $kelasId,
                    // 'tahun_ajaran_id' => ... (Dihapus)
                    'semester_id' => $semesterAktif->id, 
                    'is_active'   => 1, 
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            return $siswa;
        });
    }

    public function getMessages(): array
    {
        return $this->importMessages;
    }
}