<?php

namespace App\Imports;

use App\Models\Siswa;
use App\Models\User;
use App\Models\Kelas;
use App\Models\OrangTua;
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

        if (empty($row['nis']) || empty($row['nama_lengkap'])) {
            $this->importMessages[] = "Baris {$this->rows}: Dilewati karena NIS atau Nama Lengkap kosong.";
            return null;
        }

        $semesterAktif = DB::table('semesters')->where('is_active', 1)->first();
        
        if (!$semesterAktif) {
            $this->importMessages[] = "Baris {$this->rows}: Gagal Import. Pastikan Semester aktif sudah diatur.";
            return null;
        }

        $namaKelasInput = isset($row['kelas']) ? trim($row['kelas']) : null;
        $kelasId = null;

        if (!empty($namaKelasInput)) {
            $kelas = Kelas::where('is_active', 1)
                ->where(function($query) use ($namaKelasInput) {
                    $query->where('nama_kelas', $namaKelasInput)
                          ->orWhere('id', $namaKelasInput);
                })
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

        $existingSiswa = Siswa::where('nis', $row['nis'])->first();

        return DB::transaction(function () use ($row, $kelasId, $semesterAktif, $existingSiswa) {
            if ($existingSiswa instanceof Siswa) {
                $oldIsActive = $existingSiswa->is_active;

                $existingSiswa->update([
                    'nisn'           => $row['nisn'] ?? $existingSiswa->nisn,
                    'nik'            => $row['nik'] ?? $existingSiswa->nik,
                    'nama_lengkap'   => $row['nama_lengkap'],
                    'tempat_lahir'   => $row['tempat_lahir'] ?? $existingSiswa->tempat_lahir,
                    'tanggal_lahir'  => $row['tanggal_lahir'] ?? $existingSiswa->tanggal_lahir,
                    'jenis_kelamin'  => $row['jenis_kelamin'] ?? $existingSiswa->jenis_kelamin,
                    'agama'          => $row['agama'] ?? $existingSiswa->agama,
                    'tahun_angkatan' => $row['tahun_angkatan'] ?? $existingSiswa->tahun_angkatan,
                    'is_active'      => 1,
                    'no_telp_siswa'  => $row['no_telp_siswa'] ?? $existingSiswa->no_telp_siswa,
                    'alamat'         => $row['alamat'] ?? $existingSiswa->alamat,
                ]);

                if ($existingSiswa->user_id) {
                    User::where('id', $existingSiswa->user_id)->update([
                        'username'  => $row['nis'],
                        'is_active' => 1,
                        'password'  => Hash::make($row['nis'])
                    ]);
                }

                if ($oldIsActive == 0) {
                    $orangTuaIds = DB::table('orangtua_siswa')
                        ->where('siswa_id', $existingSiswa->id)
                        ->pluck('orangtua_id');

                    if ($orangTuaIds->isNotEmpty()) {
                        $orangtuaList = OrangTua::whereIn('id', $orangTuaIds)->get();
                        
                        foreach ($orangtuaList as $ot) {
                            if ($ot instanceof OrangTua) {
                                $ot->update(['is_active' => 1]);
                                
                                if ($ot->user_id) {
                                    User::where('id', $ot->user_id)->update([
                                        'is_active' => 1,
                                        'password'  => Hash::make((string)$ot->telepon)
                                    ]);
                                }
                            }
                        }
                    }
                }

                DB::table('siswa_kelas')
                    ->where('siswa_id', $existingSiswa->id)
                    ->update(['is_active' => 0]);

                DB::table('siswa_kelas')->updateOrInsert(
                    [
                        'siswa_id'    => $existingSiswa->id, 
                        'semester_id' => $semesterAktif->id
                    ],
                    [
                        'kelas_id'    => $kelasId, 
                        'is_active'   => 1,
                        'updated_at'  => now(),
                        'created_at'  => DB::raw('IFNULL(created_at, NOW())')
                    ]
                );

                $this->importMessages[] = "Baris {$this->rows}: Siswa {$row['nis']} diaktifkan kembali.";
                return $existingSiswa;

            } else {
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

                $lastSiswa = Siswa::where('id', 'like', 'S%')
                    ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                    ->lockForUpdate()
                    ->first();

                $lastSiswaId = $lastSiswa ? (int) substr($lastSiswa->id, 1) : 0;
                $newSiswaId = 'S' . str_pad($lastSiswaId + 1, 3, '0', STR_PAD_LEFT);

                $siswa = Siswa::create([
                    'id'            => $newSiswaId,
                    'user_id'       => $newUserId,
                    'nis'           => $row['nis'],
                    'nisn'          => $row['nisn'] ?? null,
                    'nik'           => $row['nik'] ?? null,
                    'nama_lengkap'  => $row['nama_lengkap'],
                    'tempat_lahir'  => $row['tempat_lahir'] ?? null,
                    'tanggal_lahir' => $row['tanggal_lahir'] ?? null,
                    'jenis_kelamin' => $row['jenis_kelamin'] ?? null,
                    'agama'         => $row['agama'] ?? null,
                    'tahun_angkatan'=> $row['tahun_angkatan'] ?? null,
                    'is_active'     => 1,
                    'no_telp_siswa' => $row['no_telp_siswa'] ?? null,
                    'alamat'        => $row['alamat'] ?? null,
                ]);

                DB::table('siswa_kelas')->insert([
                    'siswa_id'    => $newSiswaId,
                    'kelas_id'    => $kelasId,
                    'semester_id' => $semesterAktif->id, 
                    'is_active'   => 1, 
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                return $siswa;
            }
        });
    }

    public function getMessages(): array
    {
        return $this->importMessages;
    }
}