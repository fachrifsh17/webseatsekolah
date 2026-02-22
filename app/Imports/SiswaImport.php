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
    // Properti untuk menampung pesan catatan/konflik
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

        // 2. Cek apakah Siswa sudah terdaftar (Berdasarkan NIS atau NISN)
        $existingSiswa = Siswa::where('nis', $row['nis'])
            ->when(!empty($row['nisn']), function ($q) use ($row) {
                return $q->orWhere('nisn', $row['nisn']);
            })
            ->first();

        if ($existingSiswa) {
            $this->importMessages[] = "Baris {$this->rows}: Siswa dengan NIS '{$row['nis']}' sudah terdaftar (Gagal Import).";
            return null;
        }

        return DB::transaction(function () use ($row) {
            // Generate User ID (U001, dst)
            $lastUser = User::where('id', 'like', 'U%')
                ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                ->lockForUpdate()
                ->first();
                
            $lastUserId = $lastUser ? (int) substr($lastUser->id, 1) : 0;
            $newUserId = 'U' . str_pad($lastUserId + 1, 3, '0', STR_PAD_LEFT);

            // --- PERUBAHAN DISINI: Tambahkan current_role ---
            User::create([
                'id'           => $newUserId,
                'username'     => $row['nis'], 
                'password'     => Hash::make($row['nis']),
                'current_role' => 'Siswa', // Menetapkan role aktif default
                'is_active'    => 1,
            ]);

            // Assign Role Siswa (R003) ke tabel pivot
            DB::table('user_roles')->insert([
                'user_id'    => $newUserId,
                'role_id'    => 'R003', 
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Generate Siswa ID (S001, dst)
            $lastSiswa = Siswa::where('id', 'like', 'S%')
                ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                ->lockForUpdate()
                ->first();

            $lastSiswaId = $lastSiswa ? (int) substr($lastSiswa->id, 1) : 0;
            $newSiswaId = 'S' . str_pad($lastSiswaId + 1, 3, '0', STR_PAD_LEFT);

            // Cari Kelas Berdasarkan Tahun Ajaran Aktif
            $tahunAjaranAktif = DB::table('tahun_ajaran')
                ->where('is_active', 1)
                ->first();

            $kelas = null;
            if ($tahunAjaranAktif && !empty($row['kelas'])) {
                $kelas = Kelas::where('nama_kelas', trim($row['kelas']))
                    ->where('tahun_ajaran_id', $tahunAjaranAktif->id)
                    ->first();
            }

            return new Siswa([
                'id'            => $newSiswaId,
                'user_id'       => $newUserId,
                'nis'           => $row['nis'],
                'nisn'          => $row['nisn'] ?? null,
                'nama_lengkap'  => $row['nama_lengkap'],
                'tempat_lahir'  => $row['tempat_lahir'] ?? null,
                'tanggal_lahir' => $row['tanggal_lahir'] ?? null,
                'jenis_kelamin' => $row['jenis_kelamin'] ?? null,
                'kelas_id'      => $kelas ? $kelas->id : null,
                'is_active'     => 1,
                'no_telp_siswa' => $row['no_telp_siswa'] ?? null,
                'alamat'        => $row['alamat'] ?? null,
            ]);
        });
    }

    public function getMessages(): array
    {
        return $this->importMessages;
    }
}