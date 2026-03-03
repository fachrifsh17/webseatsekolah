<?php

namespace App\Imports;

use App\Models\GuruStaf;
use App\Models\User;
use App\Models\Jurusan;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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

        // 2. Cek apakah Guru sudah pernah terdaftar (berdasarkan NIP atau NUPTK)
        $existingGuru = null;
        if (!empty($nip)) {
            $existingGuru = GuruStaf::where('nip', $nip)->first();
        }
        
        if (!$existingGuru && !empty($nuptk)) {
            $existingGuru = GuruStaf::where('nuptk', $nuptk)->first();
        }

        return DB::transaction(function () use ($row, $nip, $nuptk, $nama, $namaJurusan, $existingGuru) {
            
            // --- LOGIKA JIKA GURU SUDAH ADA ---
            if ($existingGuru) {
                // Update data guru dan aktifkan
                $existingGuru->update([
                    'nama'               => $nama,
                    'no_hp'              => $row['no_hp'] ?? $row['telepon'] ?? $existingGuru->no_hp,
                    'email'              => $row['email'] ?? $existingGuru->email,
                    'is_active'          => 1,
                ]);

                // Aktifkan User dan Reset Password
                if ($existingGuru->user_id) {
                    $user = User::find($existingGuru->user_id);
                    if ($user) {
                        $user->update([
                            'is_active' => 1,
                            'password'  => Hash::make($user->username) // Reset ke username-nya
                        ]);
                    }
                }

                $this->importMessages[] = "Baris {$this->rows}: Guru '{$nama}' ditemukan dan telah diaktifkan kembali.";
                return null; // Return null karena kita hanya update, bukan create model baru
            }

            // --- LOGIKA JIKA GURU BARU ---
            
            // Generate User ID
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

            User::create([
                'id'           => $newUserId,
                'username'     => $finalUsername, 
                'password'     => Hash::make($finalUsername),
                'current_role' => 'Guru',
                'is_active'    => 1,
            ]);

            DB::table('user_roles')->insert([
                'user_id'    => $newUserId,
                'role_id'    => 'R002', 
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Cari Jurusan
            $jurusanId = null;
            if ($namaJurusan) {
                $jurusan = Jurusan::where('nama_jurusan', 'LIKE', '%' . $namaJurusan . '%')
                    ->where('is_active', 1)
                    ->first();
                $jurusanId = $jurusan ? $jurusan->id : null;
            }

            $formattedTanggalLahir = null;
            if(isset($row['tanggal_lahir'])) {
                try {
                    $formattedTanggalLahir = Carbon::parse($row['tanggal_lahir'])->format('Y-m-d');
                } catch (\Exception $e) { $formattedTanggalLahir = null; }
            }

            return GuruStaf::create([
                'user_id'             => $newUserId,
                'nip'                 => $nip,
                'nuptk'               => $nuptk,
                'nama'                => $nama,
                'no_hp'               => $row['no_hp'] ?? $row['telepon'] ?? null,
                'email'               => $row['email'] ?? null,
                'alamat_lengkap'      => $row['alamat_lengkap'] ?? $row['alamat'] ?? null,
                'jenis_kelamin'       => $row['jenis_kelamin'] ?? $row['jk'] ?? null,
                'tempat_lahir'        => $row['tempat_lahir'] ?? null,
                'tanggal_lahir'       => $formattedTanggalLahir,
                'agama'               => $row['agama'] ?? null,
                'pendidikan_terakhir' => $row['pendidikan_terakhir'] ?? $row['pendidikan'] ?? null,
                'jabatan_fungsional'  => $row['jabatan_fungsional'] ?? $row['jabatan'] ?? null,
                'status_kepegawaian'  => $row['status_kepegawaian'] ?? $row['status'] ?? null,
                'jurusan_id'          => $jurusanId,
                'is_active'           => 1,
            ]);
        });
    }

    public function getMessages(): array
    {
        return $this->importMessages;
    }
}