<?php

namespace App\Imports;

use App\Models\GuruStaf;
use App\Models\User;
use App\Models\Jurusan;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class GuruImport implements ToModel, WithHeadingRow
{
    public array $importMessages = [];
    private int $rows = 0;

    public function model(array $row)
    {
        $this->rows++;
        
        $nip = isset($row['nip']) ? trim($row['nip']) : null;
        $nama = isset($row['nama']) ? trim($row['nama']) : null;
        $namaJurusan = isset($row['jurusan']) ? trim($row['jurusan']) : null;

        if (empty($nama)) {
            $this->importMessages[] = "Baris {$this->rows}: Nama kosong (Dilewati).";
            return null;
        }

        if (!empty($nip)) {
            $existingGuru = GuruStaf::where('nip', $nip)->first();
            if ($existingGuru) {
                $this->importMessages[] = "Baris {$this->rows}: Guru dengan NIP '{$nip}' sudah terdaftar.";
                return null;
            }
        }

        return DB::transaction(function () use ($row, $nip, $nama, $namaJurusan) {
            // --- GENERATE USER ID ---
            $lastUser = User::where('id', 'like', 'U%')
                ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                ->lockForUpdate()
                ->first();
                
            $lastId = $lastUser ? (int) substr($lastUser->id, 1) : 0;
            $newUserId = 'U' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);

            $username = !empty($nip) ? $nip : strtolower(str_replace(' ', '', $nama));
            
            $finalUsername = $username;
            $count = 1;
            while (User::where('username', $finalUsername)->exists()) {
                $finalUsername = $username . $count;
                $count++;
            }

            // --- BUAT USER DENGAN CURRENT ROLE ---
            User::create([
                'id'           => $newUserId,
                'username'     => $finalUsername, 
                'password'     => Hash::make($finalUsername),
                'current_role' => 'Guru', // Set role aktif default
                'is_active'    => 1,
            ]);

            DB::table('user_roles')->insert([
                'user_id'    => $newUserId,
                'role_id'    => 'R002', 
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $jurusanId = null;
            if ($namaJurusan) {
                $jurusan = Jurusan::where('nama_jurusan', 'LIKE', '%' . $namaJurusan . '%')
                    ->where('is_active', 1)
                    ->first();

                if ($jurusan) {
                    $jurusanId = $jurusan->id;
                } else {
                    $this->importMessages[] = "Baris {$this->rows}: Jurusan '{$namaJurusan}' tidak ditemukan atau sedang tidak aktif.";
                }
            }

            // --- SIMPAN DATA KE TABEL GURU_STAFS ---
            return new GuruStaf([
                'user_id'            => $newUserId,
                'nip'                => $nip,
                'nuptk'              => $row['nuptk'] ?? null,
                'nama'               => $nama, // Nama lengkap disimpan di sini
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