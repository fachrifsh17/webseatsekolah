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
        $activeSemester = Semester::where('is_active', true)->first();
        $activeSemesterId = $activeSemester ? $activeSemester->id : null;

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            if (empty($row['nama_lengkap']) || empty($row['telepon'])) {
                $this->importMessages[] = "Baris {$line}: Nama lengkap dan telepon wajib diisi.";
                continue;
            }

            try {
                DB::transaction(function () use ($row, $line, $activeSemesterId) {
                    $existingOrangtua = Orangtua::where('telepon', $row['telepon'])->first();

                    if ($existingOrangtua) {
                        $existingOrangtua->update([
                            'nama_lengkap' => $row['nama_lengkap'],
                            'is_active'    => 1,
                        ]);

                        if ($existingOrangtua->user_id) {
                            User::where('id', $existingOrangtua->user_id)->update([
                                'is_active' => 1,
                                'password'  => Hash::make($row['telepon'])
                            ]);
                        }
                        
                        $ortuaId = $existingOrangtua->id;
                        $this->importMessages[] = "Baris {$line}: Data orang tua {$row['telepon']} diaktifkan kembali.";
                    } else {
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
                            'role_id'    => 'R004',
                            'created_at' => now(), 
                            'updated_at' => now(),
                        ]);

                        $lastOrtua = Orangtua::where('id', 'like', 'O%')
                            ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                            ->lockForUpdate()->first();
                        $lastOrtuaId = $lastOrtua ? (int) substr($lastOrtua->id, 1) : 0;
                        $ortuaId = 'O' . str_pad($lastOrtuaId + 1, 3, '0', STR_PAD_LEFT);

                        Orangtua::create([
                            'id'           => $ortuaId,
                            'user_id'      => $newUserId,
                            'nama_lengkap' => $row['nama_lengkap'],
                            'telepon'      => $row['telepon'],
                            'is_active'    => 1,
                        ]);
                    }

                    if (!empty($row['nis_anak'])) {
                        $nisList = explode(',', $row['nis_anak']);
                        $hubunganInput = strtolower(trim($row['hubungan'] ?? 'ayah'));

                        foreach ($nisList as $nis) {
                            $nisClean = trim($nis);
                            
                            $siswa = Siswa::where('nis', $nisClean)
                                ->where('is_active', 1)
                                ->whereHas('riwayatKelas', function($q) use ($activeSemesterId) {
                                    $q->where('semester_id', $activeSemesterId);
                                })->first();

                            if (!$siswa) {
                                throw new \Exception("Siswa NIS {$nisClean} tidak ditemukan atau tidak aktif di Semester ini.");
                            }

                            $existsRelasi = DB::table('orangtua_siswa')
                                ->where('siswa_id', $siswa->id)
                                ->where('orangtua_id', $ortuaId)
                                ->exists();

                            if (!$existsRelasi) {
                                DB::table('orangtua_siswa')->insert([
                                    'orangtua_id' => $ortuaId,
                                    'siswa_id'    => $siswa->id,
                                    'hubungan'    => $hubunganInput,
                                    'created_at'  => now(), 
                                    'updated_at'  => now(),
                                ]);
                            }
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