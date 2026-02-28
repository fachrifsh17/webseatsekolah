<?php

namespace App\Imports;

use App\Models\GuruStaf;
use App\Models\User;
use App\Models\Jurusan;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon; // Tambahkan ini untuk memproses tanggal

class GuruImport implements ToModel, WithHeadingRow
{
    public array $importMessages = [];
    private int $rows = 0;

    public function model(array $row)
    {
        $this->rows++;
        
        $nip = isset($row['nip']) ? trim($row['nip']) : null;
        $nuptk = isset($row['nuptk']) ? trim($row['nuptk']) : null;
        $nama = isset($row['nama']) ? trim($row['nama']) : null;
        $namaJurusan = isset($row['jurusan']) ? trim($row['jurusan']) : null;

        // 1. Validasi Nama Wajib
        if (empty($nama)) {
            $this->importMessages[] = "Baris {$this->rows}: Nama kosong (Dilewati).";
            return null;
        }

        // 2. Cek Duplikat NIP
        if (!empty($nip)) {
            if (GuruStaf::where('nip', $nip)->exists()) {
                $this->importMessages[] = "Baris {$this->rows}: Guru dengan NIP '{$nip}' sudah terdaftar.";
                return null;
            }
        }

        // 3. Cek Duplikat NUPTK
        if (!empty($nuptk)) {
            if (GuruStaf::where('nuptk', $nuptk)->exists()) {
                $this->importMessages[] = "Baris {$this->rows}: Guru dengan NUPTK '{$nuptk}' sudah terdaftar.";
                return null;
            }
        }

        return DB::transaction(function () use ($row, $nip, $nuptk, $nama, $namaJurusan) {
            // --- GENERATE USER ID ---
            $lastUser = User::where('id', 'like', 'U%')
                ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                ->lockForUpdate()
                ->first();
                
            $lastId = $lastUser ? (int) substr($lastUser->id, 1) : 0;
            $newUserId = 'U' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);

            $username = !empty($nip) ? $nip : strtolower(str_replace(' ', '', $nama));
            
            // Cek jika username sudah dipakai user lain
            $finalUsername = $username;
            $count = 1;
            while (User::where('username', $finalUsername)->exists()) {
                $finalUsername = $username . $count;
                $count++;
            }

            // --- BUAT USER ---
            User::create([
                'id'           => $newUserId,
                'username'     => $finalUsername, 
                'password'     => Hash::make($finalUsername),
                'current_role' => 'Guru',
                'is_active'    => 1,
            ]);

            // Assign Role R002 (Guru)
            DB::table('user_roles')->insert([
                'user_id'    => $newUserId,
                'role_id'    => 'R002', 
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Cari Jurusan ID
            $jurusanId = null;
            if ($namaJurusan) {
                $jurusan = Jurusan::where('nama_jurusan', 'LIKE', '%' . $namaJurusan . '%')
                    ->where('is_active', 1)
                    ->first();
                if ($jurusan) {
                    $jurusanId = $jurusan->id;
                } else {
                    $this->importMessages[] = "Baris {$this->rows}: Jurusan '{$namaJurusan}' tidak ditemukan.";
                }
            }

            // --- FUNGSI HELPER TANGGAL ---
            $formattedTanggalLahir = null;
            if(isset($row['tanggal_lahir'])) {
                try {
                    // Coba format YYYY-MM-DD
                    $formattedTanggalLahir = Carbon::parse($row['tanggal_lahir'])->format('Y-m-d');
                } catch (\Exception $e) {
                    $formattedTanggalLahir = null;
                }
            }

            // --- SIMPAN DATA GURU (TAMBAHKAN KOLOM BARU) ---
            return new GuruStaf([
                'user_id'            => $newUserId,
                'nip'                => $nip,
                'nuptk'              => $nuptk,
                'nama'               => $nama,
                // Kolom Baru dari Excel
                'no_hp'              => $row['no_hp'] ?? $row['telepon'] ?? null,
                'email'              => $row['email'] ?? null,
                'alamat_lengkap'     => $row['alamat_lengkap'] ?? $row['alamat'] ?? null,
                'jenis_kelamin'      => $row['jenis_kelamin'] ?? $row['jk'] ?? null,
                'tempat_lahir'       => $row['tempat_lahir'] ?? null,
                'tanggal_lahir'      => $formattedTanggalLahir,
                'agama'              => $row['agama'] ?? null,
                'pendidikan_terakhir'=> $row['pendidikan_terakhir'] ?? $row['pendidikan'] ?? null,
                // Kolom Lama
                'jabatan_fungsional' => $row['jabatan_fungsional'] ?? $row['jabatan'] ?? null,
                'status_kepegawaian' => $row['status_kepegawaian'] ?? $row['status'] ?? null,
                'jurusan_id'         => $jurusanId,
                'is_active'          => 1,
                'foto'               => null,
            ]);
        });
    }

    public function getMessages(): array
    {
        return $this->importMessages;
    }
}