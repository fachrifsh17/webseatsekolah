<?php

namespace App\Imports;

use App\Models\Orangtua;
use App\Models\User;
use App\Models\Siswa;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class OrangtuaImport implements ToCollection, WithHeadingRow
{
    public $importMessages = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $line = $index + 2;

            // 1. Validasi Input Dasar
            if (empty($row['nama_lengkap']) || empty($row['telepon'])) {
                $this->importMessages[] = "Baris {$line}: Nama lengkap dan telepon wajib diisi.";
                continue;
            }

            // 2. Cek Conflict: Orang Tua Sudah Ada
            $existingOrangtua = Orangtua::where('telepon', $row['telepon'])->first();
            if ($existingOrangtua) {
                $this->importMessages[] = "Baris {$line}: Orang tua dengan nomor {$row['telepon']} sudah terdaftar.";
                continue;
            }

            // 3. Proses Database
            try {
                DB::transaction(function () use ($row, $line) {
                    // --- GENERATE USER ID ---
                    $lastUser = User::where('id', 'like', 'U%')
                        ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                        ->lockForUpdate()->first();
                    $lastId = $lastUser ? (int) substr($lastUser->id, 1) : 0;
                    $newUserId = 'U' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);

                    // --- BUAT USER DENGAN CURRENT ROLE ---
                    User::create([
                        'id'           => $newUserId,
                        'username'     => $row['telepon'],
                        'password'     => Hash::make($row['telepon']),
                        'current_role' => 'Orangtua', // Set role aktif default
                        'is_active'    => 1,
                    ]);

                    DB::table('user_roles')->insert([
                        'user_id'    => $newUserId,
                        'role_id'    => 'R004', // Role ID untuk Orang Tua
                        'created_at' => now(), 
                        'updated_at' => now(),
                    ]);

                    // --- GENERATE ORANG TUA ID ---
                    $lastOrtua = Orangtua::where('id', 'like', 'O%')
                        ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                        ->lockForUpdate()->first();
                    $lastOrtuaId = $lastOrtua ? (int) substr($lastOrtua->id, 1) : 0;
                    $newOrtuaId = 'O' . str_pad($lastOrtuaId + 1, 3, '0', STR_PAD_LEFT);

                    Orangtua::create([
                        'id'           => $newOrtuaId,
                        'user_id'      => $newUserId,
                        'nama_lengkap' => $row['nama_lengkap'], // Nama profil disimpan di sini
                        'telepon'      => $row['telepon'],
                        'is_active'    => 1,
                    ]);

                    // --- RELASI ANAK & CEK KONFLIK NIS ---
                    if (!empty($row['nis_anak'])) {
                        $nisList = explode(',', $row['nis_anak']);
                        foreach ($nisList as $nis) {
                            $nisClean = trim($nis);
                            $siswa = Siswa::where('nis', $nisClean)
                                ->where('is_active', 1)
                                ->whereHas('kelas', function($q) {
                                    $q->whereHas('tahunAjaran', function($ta) {
                                        $ta->where('is_active', 1);
                                    });
                                })->first();

                            if (!$siswa) {
                                throw new \Exception("Siswa NIS {$nisClean} tidak ditemukan atau tidak aktif di Tahun Ajaran ini.");
                            }

                            $existsRelasi = DB::table('orangtua_siswa')
                                ->where('siswa_id', $siswa->id)
                                ->where('hubungan', strtolower($row['hubungan'] ?? 'ayah'))
                                ->exists();

                            if ($existsRelasi) {
                                throw new \Exception("Siswa {$siswa->nama_lengkap} sudah memiliki relasi " . ($row['hubungan'] ?? 'ayah') . ".");
                            }

                            DB::table('orangtua_siswa')->insert([
                                'orangtua_id' => $newOrtuaId,
                                'siswa_id'    => $siswa->id,
                                'hubungan'    => strtolower($row['hubungan'] ?? 'ayah'),
                                'created_at'  => now(), 
                                'updated_at'  => now(),
                            ]);
                        }
                    }
                });
            } catch (\Exception $e) {
                $this->importMessages[] = "Baris {$line}: " . $e->getMessage();
            }
        }
    }

    public function getMessages()
    {
        return $this->importMessages;
    }
}