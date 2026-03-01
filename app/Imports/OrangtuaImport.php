<?php

namespace App\Imports;

use App\Models\Orangtua;
use App\Models\User;
use App\Models\Siswa;
use App\Models\Semester;
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
        // Mendapatkan ID Semester yang sedang aktif
        $activeSemester = Semester::where('is_active', true)->first();
        $activeSemesterId = $activeSemester ? $activeSemester->id : null;

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            if (empty($row['nama_lengkap']) || empty($row['telepon'])) {
                $this->importMessages[] = "Baris {$line}: Nama lengkap dan telepon wajib diisi.";
                continue;
            }

            $existingOrangtua = Orangtua::where('telepon', $row['telepon'])->first();
            if ($existingOrangtua) {
                $this->importMessages[] = "Baris {$line}: Orang tua dengan nomor {$row['telepon']} sudah terdaftar.";
                continue;
            }

            try {
                DB::transaction(function () use ($row, $line, $activeSemesterId) {
                    // Generate User ID otomatis (U001, U002, dst)
                    $lastUser = User::where('id', 'like', 'U%')
                        ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                        ->lockForUpdate()->first();
                    $lastId = $lastUser ? (int) substr($lastUser->id, 1) : 0;
                    $newUserId = 'U' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);

                    User::create([
                        'id'           => $newUserId,
                        'username'     => $row['telepon'],
                        'password'     => Hash::make($row['telepon']),
                        'current_role' => 'Orangtua',
                        'is_active'    => 1,
                    ]);

                    DB::table('user_roles')->insert([
                        'user_id'    => $newUserId,
                        'role_id'    => 'R004', // Asumsi R004 adalah role Orang Tua
                        'created_at' => now(), 
                        'updated_at' => now(),
                    ]);

                    // Generate Orangtua ID otomatis (O001, O002, dst)
                    $lastOrtua = Orangtua::where('id', 'like', 'O%')
                        ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                        ->lockForUpdate()->first();
                    $lastOrtuaId = $lastOrtua ? (int) substr($lastOrtua->id, 1) : 0;
                    $newOrtuaId = 'O' . str_pad($lastOrtuaId + 1, 3, '0', STR_PAD_LEFT);

                    Orangtua::create([
                        'id'           => $newOrtuaId,
                        'user_id'      => $newUserId,
                        'nama_lengkap' => $row['nama_lengkap'],
                        'telepon'      => $row['telepon'],
                        'is_active'    => 1,
                    ]);

                    if (!empty($row['nis_anak'])) {
                        $nisList = explode(',', $row['nis_anak']);
                        $hubunganInput = strtolower(trim($row['hubungan'] ?? 'ayah'));

                        foreach ($nisList as $nis) {
                            $nisClean = trim($nis);
                            
                            // Mencari siswa yang aktif pada SEMESTER yang aktif
                            $siswa = Siswa::where('nis', $nisClean)
                                ->where('is_active', 1)
                                ->whereHas('riwayatKelas', function($q) use ($activeSemesterId) {
                                    $q->where('semester_id', $activeSemesterId);
                                })->first();

                            if (!$siswa) {
                                throw new \Exception("Siswa NIS {$nisClean} tidak ditemukan atau tidak aktif di Semester ini.");
                            }

                            // Cek apakah relasi sudah ada untuk mencegah duplikasi data
                            $existsRelasi = DB::table('orangtua_siswa')
                                ->where('siswa_id', $siswa->id)
                                ->where('hubungan', $hubunganInput)
                                ->exists();

                            if ($existsRelasi) {
                                throw new \Exception("Siswa {$siswa->nama_lengkap} sudah memiliki relasi " . $hubunganInput . ".");
                            }

                            DB::table('orangtua_siswa')->insert([
                                'orangtua_id' => $newOrtuaId,
                                'siswa_id'    => $siswa->id,
                                'hubungan'    => $hubunganInput,
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