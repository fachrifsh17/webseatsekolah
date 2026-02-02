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
    public function model(array $row)
    {
        if (empty($row['nis']) || empty($row['nama_lengkap'])) {
            return null;
        }

        $existingSiswa = Siswa::where('nis', $row['nis'])
            ->orWhere('nisn', $row['nisn'] ?? null)
            ->first();

        if ($existingSiswa) {
            return null;
        }

        return DB::transaction(function () use ($row) {
            $lastUser = User::where('id', 'like', 'U%')
                ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                ->first();
                
            $lastId = $lastUser ? (int) substr($lastUser->id, 1) : 0;
            $newUserId = 'U' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);

            User::create([
                'id'        => $newUserId,
                'username'  => $row['nis'], 
                'password'  => Hash::make($row['nis']),
                'is_active' => 1,
            ]);

            DB::table('user_roles')->insert([
                'user_id'    => $newUserId,
                'role_id'    => 'R003', 
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $kelas = Kelas::where('nama_kelas', $row['kelas'])->first();

            return new Siswa([
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
}